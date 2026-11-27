"""Descarga de pesos oficiales durante instalación; nunca procesa documentos."""
import hashlib,sys,urllib.request
from pathlib import Path
path=Path(sys.argv[1]);path.mkdir(parents=True,exist_ok=True)
target=path/'ppocrv5-server.onnx'
digest='e09385400eaaaef34ceff54aeb7c4f0f1fe014c27fa8b9905d4709b65746562a'
if not target.exists() or hashlib.sha256(target.read_bytes()).hexdigest()!=digest:
    urllib.request.urlretrieve('https://www.modelscope.cn/models/RapidAI/RapidOCR/resolve/v3.10.0/onnx/PP-OCRv5/rec/ch_PP-OCRv5_rec_server.onnx',target)
if hashlib.sha256(target.read_bytes()).hexdigest()!=digest:raise RuntimeError('Modelo OCR con checksum incorrecto')
