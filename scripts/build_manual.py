"""Genera manual PDF/HTML y diagramas SVG editables con ReportLab.
Ejecutar con el Python del runtime de documentos de Codex.
"""
from pathlib import Path
import html, json, sqlite3
from reportlab.pdfgen import canvas
from reportlab.platypus import BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table, TableStyle, PageBreak, NextPageTemplate, Flowable, Image, KeepTogether
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.enums import TA_LEFT
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.graphics.shapes import Drawing, Rect, String, Line, Circle, Ellipse, PolyLine
from reportlab.graphics import renderPDF, renderSVG

ROOT=Path(__file__).resolve().parent.parent
DOC=ROOT/'docs'; DIAG=DOC/'diagramas'; DIAG.mkdir(parents=True,exist_ok=True)
pdfmetrics.registerFont(TTFont('Segoe',r'C:\Windows\Fonts\segoeui.ttf'))
pdfmetrics.registerFont(TTFont('SegoeBold',r'C:\Windows\Fonts\segoeuib.ttf'))
pdfmetrics.registerFontFamily('Segoe',normal='Segoe',bold='SegoeBold',italic='Segoe',boldItalic='SegoeBold')
NAVY=colors.HexColor('#152e36'); GREEN=colors.HexColor('#176953'); LIGHT=colors.HexColor('#eef4f0'); LINE=colors.HexColor('#dce5df'); MUTED=colors.HexColor('#657970')
def text(d,x,y,s,size=14,bold=False,anchor='start',color=NAVY):
    d.add(String(x,y,s,fontName='SegoeBold' if bold else 'Segoe',fontSize=size,fillColor=color,textAnchor=anchor))
def line(d,points,label=None,lx=0,ly=0):
    d.add(PolyLine(points,strokeColor=MUTED,strokeWidth=1.4,fillColor=None))
    if label:text(d,lx,ly,label,12,color=GREEN)
def box(d,x,y,w,title,fields):
    h=47+len(fields)*23
    d.add(Rect(x,y,w,h,rx=6,ry=6,fillColor=colors.white,strokeColor=LINE,strokeWidth=1.3))
    d.add(Rect(x,y+h-33,w,33,fillColor=NAVY,strokeColor=NAVY))
    text(d,x+14,y+h-23,title,16,True,color=colors.white)
    for i,f in enumerate(fields):text(d,x+14,y+h-58-i*23,f,13)
    return h
def actor(d,x,y,label):
    d.add(Circle(x,y+40,12,fillColor=None,strokeColor=NAVY,strokeWidth=1.5))
    for p in [[x,y+28,x,y-10],[x-20,y+15,x+20,y+15],[x,y-10,x-18,y-34],[x,y-10,x+18,y-34]]:d.add(Line(*p,strokeColor=NAVY,strokeWidth=1.5))
    for j,t in enumerate(label.split('|')):text(d,x,y-56-j*18,t,13,True,'middle')
def ellipse(d,x,y,labels):
    d.add(Ellipse(x,y,118,31,fillColor=LIGHT,strokeColor=LINE,strokeWidth=1.3))
    for j,t in enumerate(labels.split('|')):text(d,x,y+5-(j*18) if '|' in labels else y-5,t,14,False,'middle')
def export(d,name):
    (DIAG/(name+'.svg')).write_text(renderSVG.drawToString(d),encoding='utf-8')
    return d

def usecases():
    d=Drawing(1100,800)
    d.add(Rect(205,20,690,735,rx=10,ry=10,fillColor=colors.white,strokeColor=LINE))
    text(d,550,775,'Sistema de gestión del Talento Humano',19,True,'middle')
    for x,y,label in [(80,560,'Funcionario'),(80,100,'Nómina'),(1010,650,'Jefe inmediato'),(1010,460,'Subsecretario'),(1010,275,'Alcalde'),(1010,90,'Administrador|Talento Humano')]:actor(d,x,y,label)
    for y in [675,525,375,225]:line(d,[108,570,230,y])
    line(d,[108,110,230,80])
    for points in [[980,660,870,670],[980,660,915,660,915,525,870,525],[980,470,925,470,925,670,870,670],[980,470,870,375],[980,285,870,230],[980,100,870,80]]:line(d,points)
    for y,label in [(675,'Registrar solicitud|y adjuntar soportes'),(525,'Consultar estado|y descargar PDF'),(375,'Registrar acto|administrativo'),(225,'Solicitar cancelación'),(80,'Aprobar o rechazar|acto administrativo')]:ellipse(d,350,y,label)
    for y,label in [(670,'Aprobar, devolver|o rechazar'),(525,'Consultar saldos|del periodo designado'),(375,'Resolver cancelación|de solicitud aprobada'),(230,'Revisar permiso|de alto rango'),(80,'Gestionar designaciones,|funcionarios y reglas')]:ellipse(d,750,y,label)
    text(d,550,0,'Los aprobadores también pueden ser solicitantes; no se permite autoaprobar.',12,False,'middle',MUTED)
    return export(d,'casos-de-uso')

def core_er():
    d=Drawing(1100,730)
    line(d,[285,560,400,560,400,440], '1 : N',315,570)
    line(d,[285,290,285,380,160,380,160,440], '1 : N',168,395)
    line(d,[820,565,695,565,695,440], '1 : N',728,575)
    line(d,[695,355,820,355], '1 : N',745,365)
    line(d,[550,260,550,200], '1 : N',562,220)
    line(d,[160,440,65,440,65,130,400,130], 'usuario asignado / actor',90,145)
    box(d,35,440,250,'users',['PK id','UQ identification, username','password (hash), email','department_id FK','birth_date','manager_id (legado)','roles JSON, high_rank'])
    box(d,35,220,250,'departments',['PK id','UQ name'])
    box(d,820,495,250,'categories',['PK id, UQ slug','family, name, supports','supports_required (0/1)','max_minutes','policy_confirmed, notes'])
    box(d,400,260,295,'requests',['PK id','user_id FK users','category_id FK categories','status, total_minutes','snapshot JSON, revision','justification, exception_reason','cancelled_at, timestamps'])
    box(d,820,245,250,'request_slots',['PK id','request_id FK requests','date, start_time, end_time','minutes, semester','segments JSON'])
    box(d,400,25,295,'approvals',['PK id, request_id FK','revision, step, role','assigned_user_id FK users','acted_by FK users','status, acted_at, comment'])
    text(d,45,705,'Relaciones principales',22,True)
    text(d,35,5,'PK: clave primaria · FK: clave foránea · UQ: valor único · 1:N: uno a muchos',12,color=MUTED)
    return export(d,'base-datos-tramites')
def support_er():
    d=Drawing(1100,730)
    line(d,[280,575,400,575], '1 : N',325,588)
    line(d,[550,495,550,382], '1 : N',560,440)
    line(d,[160,495,160,382], '1 : N',172,430)
    line(d,[285,310,400,310], '1 : N',325,324)
    line(d,[285,260,330,260,330,190,935,190,935,220], 'act_id opcional',550,200)
    line(d,[695,575,925,575,925,382], 'request_id opcional',755,587)
    line(d,[160,495,15,495,15,95,35,95], 'actor',20,420)
    line(d,[695,510,740,510,740,165,285,165,285,100], 'request_id opcional',390,150)
    box(d,35,495,245,'users',['PK id','name, email','roles, active'])
    box(d,400,495,295,'requests',['PK id','user_id FK users','status, total_minutes'])
    box(d,35,220,250,'acts',['PK id, user_id FK','number, granted_minutes','issued_on, expires_on','status, reviewed_by FK','UQ user_id + number'])
    box(d,400,220,295,'allocations',['PK id','request_id FK requests','act_id FK acts','minutes, state','returned_minutes'])
    box(d,805,220,260,'attachments',['PK id, user_id FK','request_id FK o act_id FK','original_name, stored_name','mime, size, sha256','created_at'])
    box(d,35,20,250,'events',['PK id, actor_id FK','request_id FK opcional','action, detail, created_at'])
    box(d,805,20,260,'outbox',['PK id, recipient','subject, body, status','attempts, last_error','created_at, sent_at'])
    text(d,45,705,'Saldos, soportes e historial',22,True)
    text(d,425,58,'Cada soporte pertenece a una solicitud o a un acto.',12,color=MUTED)
    text(d,425,38,'outbox almacena el destinatario; no tiene FK de usuario.',12,color=MUTED)
    return export(d,'base-datos-saldos')
def config_er():
    d=Drawing(1100,470)
    box(d,40,210,280,'settings',['PK key','value','Responsables y suplentes'])
    box(d,410,210,280,'holidays',['PK date','name','Días no laborables'])
    box(d,780,140,280,'schedules',['PK id','name, start_date, end_date','periods JSON','created_by FK users'])
    box(d,430,15,260,'users',['PK id','Responsable del registro'])
    line(d,[780,170,720,170,720,80,690,80],'N : 1',728,100)
    text(d,40,435,'Configuración y calendario laboral',22,True)
    text(d,40,165,'settings contiene referencias de responsables validadas por la aplicación.',14,color=MUTED)
    text(d,40,140,'El calendario base se define en WorkCalendar; schedules registra excepciones con vigencia.',14,color=MUTED)
    return export(d,'base-datos-configuracion')


def designation_er():
    d=Drawing(1100,720)
    box(d,35,445,255,'users',['PK id','birth_date','roles: incluye payroll','department_id FK'])
    box(d,35,160,255,'departments',['PK id','name'])
    box(d,390,340,315,'designations',['PK id, role','department_id FK (jefe)','user_id FK, alternate_id FK','start_date, end_date','created_by FK users','created_at'])
    box(d,805,425,265,'approvals',['PK id, request_id FK','designation_id FK','assigned_user_id FK','acted_by, acted_at'])
    box(d,735,65,335,'balance_snapshots',['PK id, user_id FK','department_id FK','created_at','data JSON inmutable'])
    line(d,[290,540,350,540,350,480,390,480],'1 : N',305,555)
    line(d,[290,230,340,230,340,390,390,390],'1 : N',295,250)
    line(d,[705,490,805,490],'1 : N',735,505)
    line(d,[290,470,315,470,315,100,735,100],'1 : N',385,115)
    text(d,35,690,'Designaciones por vigencia y consulta histórica de saldos',23,True)
    text(d,35,30,'La radicación fija al aprobador. El relevo cierra el periodo anterior y conserva los trámites pendientes.',14,color=MUTED)
    return export(d,'base-datos-designaciones')

diagrams=[usecases(),core_er(),support_er(),config_er(),designation_er()]
styles=getSampleStyleSheet()
styles.add(ParagraphStyle(name='BodyApp',fontName='Segoe',fontSize=10,leading=15,spaceAfter=9,textColor=NAVY))
styles.add(ParagraphStyle(name='TitleApp',fontName='SegoeBold',fontSize=27,leading=33,spaceAfter=18,textColor=colors.black))
styles.add(ParagraphStyle(name='H1App',fontName='SegoeBold',fontSize=18,leading=23,spaceAfter=14,textColor=colors.black,keepWithNext=True))
styles.add(ParagraphStyle(name='H2App',fontName='SegoeBold',fontSize=12,leading=17,spaceBefore=12,spaceAfter=8,textColor=colors.black,keepWithNext=True))
styles.add(ParagraphStyle(name='SmallApp',fontName='Segoe',fontSize=8,leading=11,spaceAfter=7,textColor=MUTED))
styles.add(ParagraphStyle(name='CellApp',fontName='Segoe',fontSize=8,leading=11,textColor=NAVY))
story=[];web=[]
def p(s,style='BodyApp'):
    story.append(Paragraph(s,styles[style]));web.append('<p>'+s+'</p>')
def h(s):story.append(Paragraph(s,styles['H1App']));web.append('<h2>'+html.escape(s)+'</h2>')
def sub(s):story.append(Paragraph(s,styles['H2App']));web.append('<h3>'+html.escape(s)+'</h3>')
def bullet(s):p('• '+s)
def page():story.append(PageBreak())
def table(headers,data,widths):
    cells=[[Paragraph(html.escape(str(v)),styles['CellApp']) for v in row]for row in [headers]+data]
    t=Table(cells,colWidths=widths,repeatRows=1,hAlign='LEFT')
    t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),LIGHT),('FONTNAME',(0,0),(-1,0),'SegoeBold'),('GRID',(0,0),(-1,-1),.5,LINE),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),8),('RIGHTPADDING',(0,0),(-1,-1),8),('TOPPADDING',(0,0),(-1,-1),8),('BOTTOMPADDING',(0,0),(-1,-1),8)]));story.append(t);story.append(Spacer(1,12))
    web.append('<table><thead><tr>'+''.join('<th>'+html.escape(str(x))+'</th>'for x in headers)+'</tr></thead><tbody>'+''.join('<tr>'+''.join('<td>'+html.escape(str(v))+'</td>'for v in row)+'</tr>'for row in data)+'</tbody></table>')
class Diagram(Flowable):
    def __init__(self,d):Flowable.__init__(self);self.d=d;self.factor=min(740/d.width,450/d.height);self.width=d.width*self.factor;self.height=d.height*self.factor
    def draw(self):self.canv.saveState();self.canv.scale(self.factor,self.factor);renderPDF.draw(self.d,self.canv,0,0);self.canv.restoreState()

p('ALCALDÍA MUNICIPAL DE BUCARAMANGA','SmallApp')
story.append(Paragraph('Manual de la aplicación de permisos compensatorios y licencias',styles['TitleApp']));web.append('<h1>Manual de la aplicación de permisos compensatorios y licencias</h1>')
p('Guía de uso, administración e instalación local para la gestión del Talento Humano. Describe los trámites implementados y las decisiones confirmadas durante el levantamiento de requerimientos.')
p('Versión local actualizada · 27 de octubre de 2026','SmallApp')
sub('Comenzar en este equipo')
for s in ['Ejecute iniciar.cmd desde la carpeta del proyecto y mantenga abierta su ventana mientras use la aplicación.','Abra http://127.0.0.1:8088 y consulte las cuentas iniciales en storage/acceso-local.txt.','Cambie la contraseña inicial al ingresar. El usuario admin mantiene funcionarios y responsables.','Antes de utilizar datos reales, revise la matriz de soportes, el calendario y la configuración de correo.'] :bullet(s)
sub('Contenido de esta guía')
p('Acceso y roles; registro y seguimiento de solicitudes; decisiones y cancelaciones; actos y saldos; administración e importación; calendario y reglas; correo y operación; instalación; matriz de soportes; diagramas de casos de uso y base de datos.')
p('Las cuentas y dependencias precargadas son ficticias. Los correos usan el dominio reservado ejemplo.invalid y no reciben mensajes. La autenticación con el directorio de Windows corresponde a una etapa futura.','SmallApp')
page();h('Acceso y responsabilidades')
p('Ingrese con su usuario y contraseña propios de la aplicación. El sistema exige cambiar la contraseña temporal. La nueva contraseña debe tener de 12 a 128 caracteres e incluir letras y números. La sesión vence después de 30 minutos de inactividad.')
p('Si olvida la contraseña, solicite al administrador de Talento Humano que restablezca el acceso. Ocho intentos fallidos bloquean temporalmente el ingreso durante 15 minutos.')
table(['Rol','Responsabilidades'],[
 ['Solicitante','Registra solicitudes y actos; adjunta soportes; consulta decisiones; corrige devoluciones; solicita cancelaciones y descarga PDF.'],
 ['Jefe inmediato','Realiza la primera decisión y concluye los Ordinario-Remunerado de hasta dos horas del flujo general.'],
 ['Subsecretario','Concluye el flujo general y resuelve cancelaciones de solicitudes ya aprobadas. Es el jefe de Talento Humano.'],
 ['Alcalde','Decide exclusivamente los permisos de Asesores de Despacho, Secretarios de Despacho y Jefes de Oficina.'],
 ['Nómina','Aprueba o rechaza los actos administrativos de compensatorio; registra observación, fecha y hora. No puede aprobar actos propios.'],
 ['Administrador de Talento Humano','Mantiene funcionarios, dependencias y designaciones; administra calendario, matriz y reportes. Asigna el rol de Nómina.']], [125,385])
sub('Reglas de aprobación')
bullet('Flujo general: jefe inmediato y después subsecretario. Aplica a permisos, compensatorios y ambas clases de licencias.')
bullet('Ordinario-Remunerado de hasta dos horas: solo jefe inmediato, salvo funcionarios clasificados de alto rango.')
bullet('Permisos de alto rango: solo alcalde, con independencia de la duración. Sus compensatorios y licencias siguen el flujo general.')
bullet('Nómina aprueba el cargue de actos administrativos; no agrega una etapa a la solicitud de disfrute. No se utilizan firmas digitales. Cada decisión conserva responsable, fecha y hora de Bogotá.')
page();h('Registrar y consultar una solicitud')
for s in ['Abra Nueva solicitud. Compruebe su nombre, identificación, cargo y dependencia. Si hay errores, solicite la actualización a Talento Humano.','Seleccione el tipo e ingrese una justificación de al menos diez caracteres. Para luto, indique también el parentesco.','Agregue una o varias fechas. En cada fecha elija Por horas o Día completo. Para periodos de varios días, registre cada día laborable. Se admiten hasta 90 fechas por solicitud.','Revise el total calculado y la ruta de aprobación. No se aceptan cruces con otra solicitud activa ni horarios superpuestos.','Revise si el tipo indica soporte obligatorio u opcional. Adjunte al menos un archivo si es obligatorio y pulse Enviar a aprobación. Las solicitudes devueltas conservan sus anexos.','Consulte Mis solicitudes, abra el radicado y revise el historial. Descargue el PDF con los datos, constancias e incorporación de soportes.']:bullet(s)
sub('Duración y anticipación')
p('El día ordinario equivale a 510 minutos. De lunes a jueves la jornada es 07:30 a 12:00 y 13:00 a 17:00; el viernes, 07:00 a 12:00 y 12:30 a 16:00. El cálculo excluye almuerzos, sábados, domingos y festivos. Los horarios temporales registrados sustituyen la jornada base durante su vigencia.')
p('La anticipación mínima es de 48 horas calendario dentro de días hábiles. Se cuentan las 24 horas de cada día de lunes a viernes, incluidos almuerzos y horas fuera de la jornada, y se excluyen completos los sábados, domingos y festivos. Por ejemplo, de lunes a las 08:00 a miércoles a las 08:00 se completan 48 horas si no hay festivos. Cuando no se cumple el plazo, el envío exige justificar la excepción con al menos diez caracteres. La duración del permiso sigue calculándose con la jornada laboral.')
p('Ordinario-Remunerado tiene un cupo fijo de 1.530 minutos, equivalente a 25 h 30 min, en cada semestre del año en curso: enero a junio y julio a diciembre. Los permisos de hasta dos horas también consumen cupo. Se incluyen reservas y aprobaciones; las solicitudes devueltas mantienen la reserva hasta que se corrijan, rechacen o cancelen.')
sub('Soportes y PDF')
p('Se admiten PDF, JPG y PNG, hasta 12 MB por archivo y diez archivos por carga. Una solicitud puede acumular hasta veinte soportes. Los PDF deben estar sin contraseña, ser compatibles con el importador y tener hasta cien páginas por archivo. Si un PDF se rechaza, vuelva a guardarlo como PDF compatible. Las imágenes no deben superar 25 megapíxeles.')
page();h('Revisar corregir y cancelar')
sub('Decisión del aprobador')
p('Abra Por aprobar y seleccione el radicado. Revise los datos, fechas y soportes. El sistema permite decidir únicamente al responsable de la siguiente etapa. Aprobar completa su etapa; Devolver exige motivo y habilita la corrección del solicitante; Rechazar definitivamente exige motivo y finaliza el trámite liberando las reservas.')
p('Cada radicación conserva al jefe y al subsecretario que estaban designados en esa fecha. Un relevo no traslada las etapas pendientes: el responsable anterior las termina, conservando su rol y cuenta activos. Las actuaciones ya realizadas se conservan. La aplicación bloquea la autoaprobación; si falta un suplente válido para un trámite propio, Talento Humano debe configurar la designación competente antes del envío.')
sub('Aprobar varias solicitudes')
p('El jefe inmediato y el subsecretario pueden abrir Por aprobar, marcar solicitudes individualmente o usar Seleccionar todas de esta página y pulsar Aprobar seleccionadas. Se admiten hasta cien por acción y una observación común opcional. Cada solicitud conserva su ruta, constancia y notificación. El resultado informa cuáles se aprobaron y por qué otras no pudieron procesarse, sin detener las demás. La bandeja tiene paginación y cada lote aprueba solo la etapa seleccionada.')
sub('Corregir una devolución')
p('Abra la solicitud devuelta y seleccione Corregir y reenviar. Modifique los datos necesarios y adjunte soportes adicionales. El reenvío inicia una nueva revisión desde la primera etapa, conservando el historial previo. El nuevo tiempo vuelve a validarse contra cupos y saldos.')
sub('Cancelar')
p('En una solicitud pendiente o devuelta, indique el motivo y solicite la cancelación: esta se hace efectiva y libera las reservas. Si ya está aprobada, pasa a Cancelación en revisión y conserva su efecto hasta que el subsecretario resuelva. El alcalde no reemplaza al subsecretario en este trámite de cancelación.')
p('Al autorizar una cancelación, se considera disfrutado el tiempo laborable transcurrido hasta la fecha y hora de la decisión; se reintegra el tiempo restante. Si se deniega, la solicitud conserva su aprobación. Si todo el periodo terminó, no se reintegra tiempo.')
p('Ejemplo: se aprobaron 3 horas de compensatorio, de 07:00 a 10:00 un viernes. Si el subsecretario autoriza la cancelación a las 08:00, se conserva 1 hora disfrutada y se reintegran 2 horas.','SmallApp')
table(['Estado','Significado'],[['En aprobación','Esperando la siguiente decisión.'],['Aprobada','Concluyeron todas las etapas.'],['Devuelta para corregir','Requiere modificación y reenvío; conserva reserva.'],['Rechazada','Decisión definitiva; reservas liberadas.'],['Cancelación en revisión','Solicitud aprobada pendiente de decisión de cancelación.'],['Cancelada','Cancelación efectiva; conserva el historial y tiempo disfrutado.']],[155,355])
page();h('Actos administrativos y saldos')
for s in ['Abra Actos y saldos y registre número del acto, fecha de expedición, fecha de vencimiento y total de horas otorgadas. Adjunte el documento del acto.','Un usuario con rol de Nómina diferente del titular revisa el documento y aprueba o rechaza el registro, dejando observación. Se conserva su identidad, fecha y hora. Talento Humano sin ese rol no aprueba actos.','Solo los actos aprobados y vigentes pueden respaldar una solicitud de Disfrute por acto administrativo.','Al solicitar el disfrute, asigne las horas que se utilizarán de uno o varios actos. La suma debe coincidir exactamente con la duración de la solicitud.','Cada acto debe cubrir todas las fechas de la solicitud. Las horas se reservan al enviar, se descuentan definitivamente con la aprobación final y se liberan con el rechazo o la cancelación.']:bullet(s)
p('Un saldo vencido permanece visible para consulta histórica, pero no puede utilizarse. Una cancelación no amplía la vigencia del acto. El saldo puede recuperarse contablemente y seguir sin disponibilidad para nuevos disfrutes si el acto ya venció.')
sub('Conservación de cálculos')
p('La solicitud guarda las franjas laborables que se usaron al enviarla. Un cambio posterior del calendario o del horario no recalcula las duraciones históricas ni altera lo ya disfrutado. Si cambian las condiciones de una solicitud pendiente, devuélvala para corregir y reenviar.')
sub('Importes y unidades')
p('Todos los cálculos se guardan en minutos enteros. En campos de horas, 8,5 equivale a 510 minutos. La interfaz permite incrementos de 0,05 horas, equivalentes a tres minutos. El servicio rechaza valores que no equivalen a minutos enteros.')
page();h('Administrar funcionarios e importar Excel')
sub('Directorio y responsables')
p('En Funcionarios puede crear dependencias, registrar o editar funcionarios, su fecha de nacimiento, roles y clasificación de alto rango. La fecha de nacimiento es necesaria para Cumpleaños y solo la mantiene Talento Humano. La clasificación usa tres valores específicos: Asesor de Despacho, Secretario de Despacho y Jefe de Oficina. El cargo escrito como texto no modifica por sí mismo el flujo.')
p('En Designaciones seleccione un jefe inmediato, un suplente para solicitudes propias, una o varias dependencias y la fecha de inicio. Registre también la designación institucional del subsecretario y su suplente. El periodo anterior termina el día calendario previo; los responsables futuros no pueden aprobar anticipadamente. Los cambios guardan usuario, fecha y hora. Las fechas deben ser hoy o futuras y posteriores a la última designación del mismo alcance. El alcalde y su suplente se configuran por separado.')
sub('Importación')
for s in ['Descargue la plantilla CSV desde Importar Excel. Puede completarla y guardarla como CSV o XLSX.','Use los encabezados: cedula, nombre, usuario, correo, cargo, codigo, grado, vinculacion, dependencia, cedula_jefe, alto_rango y fecha_nacimiento (opcional, texto AAAA-MM-DD). Mantenga las cédulas como texto.','Incluya los jefes en el mismo archivo o asegúrese de que ya existan. Los campos cedula_jefe mantienen compatibilidad con listados anteriores. Las designaciones vigentes por dependencia tienen prioridad sobre ese dato individual.','Cargue un archivo de hasta 5 MB y 1.000 registros. Revise la vista previa y confirme. Una cédula existente actualiza sus datos; una nueva crea una cuenta.','Descargue las contraseñas temporales de las cuentas nuevas durante esa sesión y entregue cada acceso a su titular. Los roles administrativos existentes se conservan.']:bullet(s)
p('Si una fila no cumple la validación, se revierte toda la importación y no se crean registros parciales. El sistema no admite fórmulas. Las dependencias nuevas se crean automáticamente y los funcionarios referenciados como jefes reciben ese rol.')
sub('Reportes e historial')
p('Reportes filtra por fechas de disfrute y exporta CSV con una fila por franja solicitada. El historial administrativo muestra las últimas 300 actuaciones; cada solicitud conserva su propio historial completo. Las pantallas de listado tienen límites de filas para la revisión local, detallados en README.md y el código.')
page();h('Designaciones y consulta de saldos')
for text_item in ['Registre el responsable por dependencia desde Designaciones. Un mismo jefe puede tener varias dependencias; no es necesario editar a cada funcionario. La primera designación sustituye la configuración individual anterior a partir de su fecha de inicio. Antes de esa fecha se conserva la configuración anterior.','Un cambio no reasigna trámites radicados. Cada aprobador anterior conserva sus etapas pendientes incluso después del cierre de su periodo. Mantenga su cuenta y rol mientras haya trámites pendientes. Un reenvío después de devolución inicia una nueva revisión con los responsables vigentes al reenviar.','Los suplentes se registran por dependencia y para el subsecretario; se usan para solicitudes del propio responsable. Si falta un suplente competente, el envío se bloquea.','En Consulta de saldos seleccione una fecha de corte de su periodo designado. Los jefes anteriores no ven movimientos posteriores al cierre. La consulta incluye cupo semestral ordinario, tiempo reservado y aprobado de cada tipo, vigencias de cumpleaños y actos disponibles al corte.','El historial de saldos se conserva mediante instantáneas inmutables desde esta actualización. No se fabrican saldos de fechas anteriores ni designaciones históricas sin evidencia. Talento Humano debe registrar las primeras designaciones de cada dependencia.']:bullet(text_item)
page();h('Cumpleaños')
sub('Una jornada completa por vigencia')
for text_item in ['Seleccione Cumpleaños, la vigencia del beneficio y una única fecha en modalidad Día completo. Talento Humano debe haber registrado su fecha de nacimiento.','El día puede disfrutarse en cualquier fecha de su vigencia. Para nacidos en diciembre, también se permite del 1 al 30 de enero siguiente, cargándolo al año del cumpleaños y sin consumir el beneficio del año siguiente. No se anticipan beneficios de años futuros.','Una solicitud pendiente, devuelta, aprobada o con cancelación en revisión reserva o consume el beneficio. El rechazo o la cancelación antes del disfrute lo libera; si ya se disfrutó parte de la jornada, se mantiene consumido.']:bullet(text_item)
page();h('Configurar calendario reglas y correo')
sub('Calendario y horarios temporales')
p('El calendario nacional se precarga para el año de instalación y los dos siguientes. En Calendario laboral, agregue días no laborables o retire registros futuros cuando corresponda. La duración solicitada se calcula con la jornada laboral configurada y excluye fines de semana, festivos y descansos. El funcionario no declara jornadas personalizadas en la solicitud. Para ampliar la cobertura, ejecute php scripts/calendar.php AÑO.')
p('Registre un horario temporal con fechas de inicio y fin y con la jornada completa de lunes a viernes. Se permiten hasta tres franjas por día; las franjas deben estar ordenadas y no superponerse. No se admiten vigencias temporales que se crucen. La jornada base vuelve a aplicarse cuando termina la vigencia.')
sub('Matriz de tipos y soportes')
p('En Tipos y soportes, el administrador marca o desmarca Exigir al menos un soporte para cada tipo de permiso, compensatorio o licencia y pulsa Guardar regla. Al desmarcarlo, el formulario indica Soporte opcional y permite enviar sin adjuntos. Los tipos existentes conservan su obligatoriedad hasta que Talento Humano la cambie. Los archivos que se carguen conservan las validaciones de formato y tamaño. El documento para registrar un acto administrativo que otorga saldo continúa siendo obligatorio.')
p('La obligatoriedad del soporte es independiente de Regla revisada por Talento Humano. El administrador también puede editar documentos, notas y máximo de horas por solicitud. El máximo numérico se aplica solo a reglas revisadas. El cupo semestral Ordinario-Remunerado siempre se valida.')
p('Cumpleaños tiene control automático de una jornada completa por vigencia. Las demás frecuencias y parentescos escritos en las notas son informativos hasta completar y validar la matriz.')
sub('Notificaciones')
p('Copie config/example.php a config/local.php y configure host, puerto, usuario, contraseña, cifrado, remitente y URL. Ejecute php scripts/mail-worker.php regularmente con el programador de tareas del servidor. Cada ejecución procesa hasta cincuenta correos y reintenta fallos hasta cinco veces.')
p('Sin SMTP los correos permanecen pendientes. No se ha verificado entrega contra un servidor institucional. Si el trabajador se interrumpe durante un envío, revise los mensajes con estado sending antes de reponerlos en cola para evitar duplicados.')
page();h('Instalación y operación local')
p('Requisitos: PHP 8.4 o superior compatible con composer.lock; extensiones PDO SQLite, fileinfo, mbstring, GD, zip, intl, DOM, XML, curl y OpenSSL; Composer para instalar dependencias. En este equipo se incluyó un runtime aislado en .runtime/php. No hace falta Node para ejecutar el portal.')
for s in ['En otro equipo, instale dependencias con composer install --no-dev --prefer-dist.','Inicialice con php scripts/install.php. Las cuentas iniciales se registran en storage/acceso-local.txt y deben cambiar la contraseña al ingresar.','Inicie con php -S 127.0.0.1:8088 -t public public/router.php. En este equipo puede usar iniciar.cmd.','Para cambiar parámetros, cree config/local.php a partir del ejemplo. La base de datos y los soportes deben conservar permisos de lectura y escritura para el proceso PHP.']:bullet(s)
sub('Respaldo y recuperación')
p('Ejecute php scripts/backup.php para crear una copia consistente de SQLite y de los soportes referenciados. Resguarde también config/local.php por un canal protegido. Para recuperar, detenga el servicio, conserve una copia del estado actual y restaure base y archivos del mismo respaldo antes de iniciar de nuevo.')
sub('Arquitectura y comprobaciones')
p('La aplicación usa PHP, PDO y SQLite; TCPDF/FPDI para PDF con anexos; PhpSpreadsheet para XLSX y PHPMailer para SMTP. La carpeta pública es public; app contiene las reglas; views, las pantallas; database, el esquema y catálogo; storage, los archivos privados; y scripts, las herramientas de operación.')
functional=json.loads((ROOT/'.work/test-result.json').read_text())['checks']; http_checks=json.loads((ROOT/'.work/http-result.json').read_text())['checks']
p(f'La revisión actualizada ejecutó {functional} comprobaciones funcionales y {http_checks} por HTTP. Incluye designaciones, autorización por vigencia, Nómina, cumpleaños, saldos históricos, retiro de la jornada personalizada y los flujos anteriores. Las bases de pruebas son independientes de la base local.')
p('La publicación en Internet y la integración con el directorio de Windows no se han realizado. La instalación futura requerirá servidor web con raíz en public, HTTPS y configuración institucional de sesiones, correo, acceso y respaldos.','SmallApp')
page();h('Matriz inicial de duraciones y soportes')
p('Esta matriz distingue las reglas confirmadas de las propuestas pendientes. Los valores de días presentes en el formato se conservan como referencia y no se aplican como límites automáticos adicionales sin validación de Talento Humano.')
catalog=json.loads((ROOT/'.work/catalog.json').read_text(encoding='utf-8'))
family={'permiso':'Permiso','compensatorio':'Compensatorio','remunerada':'Licencia remunerada','no_remunerada':'Licencia no remunerada'}
for f in family:
    sub(family[f])
    data=[[c[1],c[3],c[6]] for c in catalog if c[0]==f]
    table(['Tipo','Soportes propuestos','Duración y estado'],data,[120,195,195])
page();h('Fuentes y decisiones de implementación')
p('La fuente funcional principal son las instrucciones y respuestas del usuario. El formato institucional entregado es F-GAT-8100-238,37-183, versión 6.0; aporta campos y soportes. Prevalecen las decisiones expresas del usuario: Bienestar equivale a tarjeta vacacional, Quinquenio solo es permiso y Nómina aprueba únicamente el cargue de actos administrativos de compensatorio.')
p('El calendario nacional se calcula con las reglas de traslado de la Ley 51 de 1983. Incluye desde 2026 la conmemoración del 9 de julio trasladada al lunes siguiente cuando corresponde, documentada por la Circular MTIC 23 de 2026. Los días adicionales municipales se mantienen en el calendario de la aplicación.')
for title,url in [('Ley 51 de 1983 - Normograma Cancillería','https://www.cancilleria.gov.co/sites/default/files/Normograma/docs/ley_0051_1983.htm'),('Circular MTIC 23 de 2026','https://normograma.mintic.gov.co/mintic/compilacion/docs/circular_mintic_0023_2026.htm'),('Instalación FPDI y compatibilidad con TCPDF','https://manuals.setasign.com/fpdi-manual/v2/installation/'),('PHP para Windows','https://www.php.net/downloads.php?os=windows&amp;version=8.4')]:p('<link href="'+url+'" color="#176953">'+title+'</link>','SmallApp')
sub('Condiciones pendientes para la operación institucional')
bullet('Entregar el Excel real y revisar correspondencias de columnas, jefes y clasificaciones de alto rango.')
bullet('Completar y validar duraciones, frecuencias, parentescos y soportes obligatorios de los beneficios que no tienen regla confirmada.')
bullet('Proporcionar parámetros SMTP y verificar la recepción de novedades con cuentas institucionales.')
bullet('Definir responsables competentes para las solicitudes propias de aprobadores que no tengan suplente configurado.')
sub('Decisiones técnicas visibles en el funcionamiento')
p('Las solicitudes se registran para fechas y horas futuras. La aprobación final se detiene si su fecha inicial ya pasó, en cuyo caso se debe devolver para corregir. La cancelación reintegra tiempo según la hora de autorización, no según la hora de petición. Las solicitudes devueltas retienen sus reservas y un cambio de calendario no recalcula trámites existentes.')

for title,name,d in [('Casos de uso','casos-de-uso',diagrams[0]),('Base de datos de trámites','base-datos-tramites',diagrams[1]),('Base de datos de saldos y soportes','base-datos-saldos',diagrams[2]),('Base de datos de configuración','base-datos-configuracion',diagrams[3]),('Base de datos de designaciones','base-datos-designaciones',diagrams[4])]:
    story.extend([NextPageTemplate('landscape'),PageBreak()]);h(title);story.append(Diagram(d));web.append('<img class="diagram" src="diagramas/'+name+'.svg" alt="'+title+'">')
story.extend([NextPageTemplate('portrait'),PageBreak()]);h('Diccionario de tablas')
connection=sqlite3.connect(ROOT/'storage/app.sqlite')
table_names=[r[0] for r in connection.execute("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")]
dictionary=[]
for name in table_names:
    fields=connection.execute('PRAGMA table_info('+name+')').fetchall()
    dictionary.append([name,', '.join(f[1] for f in fields)])
table(['Tabla','Campos'],dictionary,[120,390]);connection.close()
p('El esquema SQL completo y sus restricciones se encuentran en database/schema.sql. Los tiempos se guardan en minutos, los roles y copias de datos del funcionario como JSON y las constancias como eventos y aprobaciones. Las contraseñas se guardan como hash; los soportes quedan fuera de la raíz pública.','SmallApp')

def footer(c,doc):
    w,hp=c._pagesize;c.saveState();c.setStrokeColor(LINE);c.line(42,35,w-42,35);c.setFont('Segoe',8);c.setFillColor(MUTED);c.drawString(42,23,'Alcaldía de Bucaramanga | Gestión del Talento Humano');c.drawRightString(w-42,23,str(doc.page));c.restoreState()
doc=BaseDocTemplate(str(DOC/'manual.pdf'),pagesize=A4,leftMargin=42,rightMargin=42,topMargin=42,bottomMargin=50,title='Manual de permisos compensatorios y licencias',author='Gestión del Talento Humano')
doc.addPageTemplates([PageTemplate(id='portrait',frames=[Frame(42,50,A4[0]-84,A4[1]-92,leftPadding=0,rightPadding=0,topPadding=0,bottomPadding=0)],onPage=footer,pagesize=A4),PageTemplate(id='landscape',frames=[Frame(42,50,landscape(A4)[0]-84,landscape(A4)[1]-92,leftPadding=0,rightPadding=0,topPadding=0,bottomPadding=0)],onPage=footer,pagesize=landscape(A4))]);doc.build(story)
css='body{font:16px/1.7 Segoe UI,Arial,sans-serif;color:#20343b;background:#f5f7f5;margin:0}main{max-width:1020px;margin:30px auto;background:white;padding:50px}h1,h2,h3{color:#000;line-height:1.3}h1{font-size:34px}h2{font-size:25px;margin-top:45px}h3{font-size:19px}p{max-width:850px}a{color:#176953}table{border-collapse:collapse;width:100%;font-size:13px;margin:20px 0}td,th{border:1px solid #dce5df;padding:12px;text-align:left;vertical-align:top}th{background:#eef4f0}.diagram{width:100%;margin:20px 0} @media(max-width:700px){main{padding:22px;margin:0}table{font-size:11px}td,th{padding:6px}}'
(DOC/'manual.html').write_text('<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Manual de Talento Humano</title><style>'+css+'</style><main>'+''.join(web)+'</main></html>',encoding='utf-8')
print('Manual PDF/HTML y cinco diagramas SVG generados.')
