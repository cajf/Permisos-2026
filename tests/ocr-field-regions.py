import ast,tempfile
from pathlib import Path
import numpy as np
script=Path(__file__).parents[1]/'scripts/ocr/read.py'
tree=ast.parse(script.read_text(encoding='utf-8'))
nodes=[node for node in tree.body if isinstance(node,ast.FunctionDef) and node.name=='field_boxes']
namespace={'Path':Path,'np':np,'norm':str.upper,'re':__import__('re')}
exec(compile(ast.Module(body=nodes,type_ignores=[]),str(script),'exec'),namespace)
def box(y,x=0):return np.array([[x,y],[x+100,y],[x+100,y+20],[x,y+20]],dtype=np.float32)
with tempfile.TemporaryDirectory() as directory:
    path=Path(directory)/'anchors.tsv'
    rows=[]
    for word,y in [('REGISTRADURIA',20),('NACIONAL',50),('CERTIFICADO',180),('ELECCIONES',220),('CEDULA',300)]:rows.append(f'5\t1\t1\t1\t1\t1\t0\t{y}\t100\t20\t99\t{word}')
    path.write_text('\n'.join(rows),encoding='utf-8')
    boxes=[box(y) for y in [20,50,180,220,300,380,600]]+[box(300,200)]
    chosen=namespace['field_boxes'](boxes,path,1)
    assert len(chosen)==6
    assert not any(np.array_equal(candidate,box(380)) or np.array_equal(candidate,box(600)) for candidate in chosen)
    assert any(np.array_equal(candidate,box(300,200)) for candidate in chosen)
    print('OK selecciona institución, tipo, fecha, etiqueta y valor contiguo de cédula; excluye nombre y firma')
    fallback=namespace['field_boxes']([box(y*30) for y in range(50)],None,1)
    assert len(fallback)==12
    print('OK limita la exploración cuando no se leen etiquetas')
