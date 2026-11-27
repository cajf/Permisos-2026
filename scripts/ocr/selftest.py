"""Prueba de instalación con datos ficticios; no requiere red ni base de datos."""
import json,subprocess,sys,tempfile
from pathlib import Path
from PIL import Image,ImageDraw,ImageFont
with tempfile.TemporaryDirectory(prefix='ocr-selftest-') as directory:
    source=Path(directory)/'ficticio.png'
    image=Image.new('RGB',(1800,650),'white');draw=ImageDraw.Draw(image)
    font=ImageFont.load_default(size=42)
    for index,line in enumerate(['REGISTRADURIA NACIONAL DEL ESTADO CIVIL','CERTIFICADO ELECTORAL','ELECCIONES 25 DE OCTUBRE DE 2026','CEDULA 1234567890']):draw.text((40,50+index*130),line,font=font,fill='black')
    image.save(source)
    result=subprocess.run([sys.executable,str(Path(__file__).with_name('read.py')),str(source),sys.argv[1]],check=True,capture_output=True,text=True,timeout=100)
    report=json.loads(result.stdout)
    text=' '.join(line['text'] for line in report['lines'])
    for expected in ['CERTIFICADO ELECTORAL','1234567890','25 DE OCTUBRE DE 2026']:
        if expected not in text:raise RuntimeError('No se completó la prueba OCR ficticia: '+expected)
    print('OCR selftest OK: PP-OCRv4 detecta y PP-OCRv5 reconoce tipo, cedula y fecha ficticios.')
