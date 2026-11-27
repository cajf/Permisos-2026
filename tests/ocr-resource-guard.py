"""Prueba la protección antes de importar modelos, sin ejecutar OCR ni usar datos personales."""
import ast,contextlib,io,json,runpy,sys,os
from pathlib import Path
from unittest.mock import patch
script=Path(__file__).parents[1]/'scripts/ocr/read.py'
for mb in [256,512,768]:
    output=io.StringIO()
    with patch.object(sys,'platform','linux'),patch.object(Path,'read_text',return_value=str(mb*1024*1024)),contextlib.redirect_stdout(output):
        try:runpy.run_path(str(script),run_name='__main__')
        except SystemExit as error:assert error.code==0
        else:raise AssertionError('No se bloqueó la carga de los modelos')
    assert json.loads(output.getvalue())['unavailable']=='memory'
    print(f'OK protección temprana con {mb} MB')
tree=ast.parse(script.read_text(encoding='utf-8'))
function=next(node for node in tree.body if isinstance(node,ast.FunctionDef) and node.name=='sufficient_memory')
namespace={'sys':sys,'os':os,'Path':Path};exec(compile(ast.Module(body=[function],type_ignores=[]),str(script),'exec'),namespace)
with patch.object(sys,'platform','linux'),patch.object(Path,'read_text',return_value=str(1024*1024*1024)):
    assert namespace['sufficient_memory']()
print('OK conserva la segunda etapa con límite de 1 GB o mayor')
with patch.object(sys,'platform','linux'),patch.object(Path,'read_text',side_effect=OSError),patch.dict(os.environ,{'RENDER':'true'}):
    assert not namespace['sufficient_memory']()
print('OK Render sin límite verificable conserva revisión manual')
