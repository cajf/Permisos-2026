"""CLI local: PP-OCRv4 detecta; PP-OCRv5 reconoce. Sin red ni base de datos."""
import sys,json,re,unicodedata,os
from pathlib import Path
# Evitar que el OOM killer reinicie el portal en alojamientos de 512 MB.
# La comprobación precede las importaciones de NumPy/OpenCV y la carga de modelos.
def sufficient_memory():
    limits=[]
    if sys.platform.startswith('linux'):
        for filename in ['/sys/fs/cgroup/memory.max','/sys/fs/cgroup/memory/memory.limit_in_bytes']:
            try:
                value=Path(filename).read_text().strip()
                if value.isdigit() and int(value)<2**60:limits.append(int(value))
            except OSError:pass
        if not limits and os.environ.get('RENDER')=='true':return False
        if not limits:
            try:
                for line in Path('/proc/meminfo').read_text().splitlines():
                    if line.startswith('MemTotal:'):limits.append(int(line.split()[1])*1024)
            except OSError:pass
    return not limits or min(limits)>=1024*1024*1024
if not sufficient_memory():
    print(json.dumps({'unavailable':'memory','lines':[],'observed_lines':[]}))
    raise SystemExit(0)
import numpy as np
from rapidocr_onnxruntime import RapidOCR
from PIL import Image,ImageOps
def norm(text):return ''.join(c for c in unicodedata.normalize('NFD',text.upper()) if unicodedata.category(c)!='Mn')
def field_boxes(boxes,tsv_path,scale):
    anchors=[]
    if tsv_path:
        for row in Path(tsv_path).read_text(encoding='utf-8').splitlines():
            cells=row.split('\t',11)
            if len(cells)!=12 or cells[0]!='5':continue
            if re.search(r'CERTIF|CONSTAN|ELECTOR|ELECCI|REGISTR|NACIONAL|COLOMBIA|CEDULA|^NUM|FECHA|JURADO|VOTACION|PREST|SERVICIO',norm(cells[11])):
                x,y,w,h=map(float,cells[6:10]);anchors.append(((y+h/2)*scale,h*scale))
    chosen=[]
    for box in boxes:
        y=float(np.mean(box[:,1]));h=float(np.max(box[:,1])-np.min(box[:,1]))
        if any(abs(y-ay)<=max(ah,h)*.95 for ay,ah in anchors):chosen.append(box)
    # Si las etiquetas son ilegibles, exploración limitada de la cabecera del documento.
    # No recorrer toda la página con el modelo de reconocimiento costoso.
    if len(chosen)<3:chosen=sorted(boxes,key=lambda box:float(np.mean(box[:,1])))[:12]
    # La institución es necesaria para confirmar que el soporte es colombiano.
    header=sorted(boxes,key=lambda box:float(np.mean(box[:,1])))[:4]
    unique=[]
    for box in header+chosen:
        if not any(np.array_equal(box,previous) for previous in unique):unique.append(box)
    return unique[:16]
def main():
    source=Path(sys.argv[1]);models=Path(sys.argv[2])
    with Image.open(source) as image:
        if image.width*image.height>25000000:raise ValueError('Imagen demasiado grande')
        image=ImageOps.exif_transpose(image).convert('RGB');original_width=image.width;image.thumbnail((3600,3600));scale=image.width/original_width;array=np.asarray(image)[:,:,::-1].copy()
    engine=RapidOCR(rec_model_path=str(models/'ppocrv5-server.onnx'),intra_op_num_threads=2,inter_op_num_threads=1,rec_batch_num=1)
    boxes,_=engine.text_det(array)
    boxes=[] if boxes is None else list(boxes)
    selected=field_boxes(boxes,sys.argv[3] if len(sys.argv)>3 else None,scale)
    if selected:
        crops=engine.get_crop_img_list(array,selected)
        readings,_=engine.text_rec(crops)
        result=[(box.tolist(),text,float(score)) for box,(text,score) in zip(selected,readings)]
    else:result=[]
    lines=[{'text':text,'confidence':float(score)*100} for box,text,score in result]
    # Etiquetas y valores a menudo aparecen en cajas diferentes de la misma fila.
    labels=[box for box,text,score in result if re.fullmatch(r'NUM\.*|CEDULA(?:\s*NO\.*)?',norm(text).strip()) and score>=.8]
    for box,text,score in result:
        if score<.8 or not re.fullmatch(r'[0-9. :,]{4,24}',text.strip()):continue
        x,y=np.mean(box,axis=0)
        for label in labels:
            lx,ly=np.mean(label,axis=0);h=max(p[1] for p in label)-min(p[1] for p in label)
            if x>lx and abs(y-ly)<=max(h,30):lines.append({'text':'CEDULA '+text,'confidence':float(score)*100})
    # Las normalizaciones afectan etiquetas y separadores, nunca sustituyen dígitos.
    for line in lines:
        text=norm(line['text'])
        text=text.replace('CERTIFICAD0','CERTIFICADO').replace('ELECT0RAL','ELECTORAL').replace('ELECCI0NES','ELECCIONES')
        text=re.sub(r'CERTIFICADO\s*ELECTORAL','CERTIFICADO ELECTORAL',text)
        text=re.sub(r'(CEDULA\s*NO\.*|NUM\.*)\s*(?=\d)',r'CEDULA ',text)
        text=re.sub(r'(?<=\d)[:,](?=\d)', '.',text)
        text=re.sub(r'(\d)(DE)(?=[A-Z])',r'\1 DE ',text)
        text=re.sub(r'(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)DE\s*(20\d{2})',r'\1 DE \2',text)
        text=re.sub(r'ELECCIONES(?=\d)','ELECCIONES ',text)
        line['text']=text
    print(json.dumps({'engine':'PP-OCRv4-detection/PP-OCRv5-recognition','lines':lines,'observed_lines':[text for box,text,score in result],'detected_regions':len(boxes),'recognized_regions':len(selected)},ensure_ascii=True))
if __name__=='__main__':main()
