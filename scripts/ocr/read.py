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
def main():
    source=Path(sys.argv[1]);models=Path(sys.argv[2])
    with Image.open(source) as image:
        if image.width*image.height>25000000:raise ValueError('Imagen demasiado grande')
        image=ImageOps.exif_transpose(image).convert('RGB');image.thumbnail((3600,3600));array=np.asarray(image)[:,:,::-1].copy()
    engine=RapidOCR(rec_model_path=str(models/'ppocrv5-server.onnx'),intra_op_num_threads=2,inter_op_num_threads=1,rec_batch_num=1)
    result,_=engine(array)
    result=result or []
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
    print(json.dumps({'engine':'PP-OCRv4-detection/PP-OCRv5-recognition','lines':lines,'observed_lines':[text for box,text,score in result]},ensure_ascii=True))
if __name__=='__main__':main()
