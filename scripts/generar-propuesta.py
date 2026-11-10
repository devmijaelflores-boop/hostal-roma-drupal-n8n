"""Actualiza el mismo Word de la propuesta; requiere python-docx."""
from pathlib import Path
from docx import Document
from docx.shared import Cm, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn

OUT = Path(__file__).resolve().parents[1] / 'docs' / 'propuesta'
OUT.mkdir(parents=True, exist_ok=True)
TARGET = OUT / 'Propuesta_academica_Hostal_ROMA.docx'
# Conserva la ruta y el archivo solicitado; sustituye su contenido y formato.
doc = Document(TARGET) if TARGET.exists() else Document()
for element in list(doc.element.body):
    if element.tag != qn('w:sectPr'):
        doc.element.body.remove(element)

section = doc.sections[0]
section.page_width, section.page_height = Cm(21), Cm(29.7)
section.top_margin, section.bottom_margin = Cm(2.1), Cm(2.0)
section.left_margin = section.right_margin = Cm(2.5)
section.header_distance = section.footer_distance = Cm(.9)
normal = doc.styles['Normal']
normal.font.name, normal.font.size = 'Times New Roman', Pt(12)
normal.font.color.rgb = RGBColor(0, 0, 0)
normal.paragraph_format.line_spacing = 1.12
normal.paragraph_format.space_after = Pt(6)
normal.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
for name, size in [('Title', 17), ('Heading 1', 13), ('Heading 2', 12)]:
    style = doc.styles[name]
    style.font.name, style.font.size = 'Times New Roman', Pt(size)
    style.font.color.rgb = RGBColor(0, 0, 0)
    style.font.bold = True
    style.paragraph_format.space_before, style.paragraph_format.space_after = Pt(9), Pt(5)
    style.paragraph_format.keep_with_next = True

# Elimina las rayas heredadas y los colores temáticos de los estilos Word.
for border in list(doc.styles.element.iter(qn('w:pBdr'))):
    border.getparent().remove(border)
for color in doc.styles.element.iter(qn('w:color')):
    color.attrib.clear()
    color.set(qn('w:val'), '000000')
for fonts in doc.styles.element.iter(qn('w:rFonts')):
    fonts.attrib.clear()
    for script in ['ascii', 'hAnsi', 'eastAsia', 'cs']:
        fonts.set(qn('w:' + script), 'Times New Roman')
for shading in doc.styles.element.iter(qn('w:shd')):
    shading.attrib.clear()
    shading.set(qn('w:val'), 'clear')
    shading.set(qn('w:fill'), 'FFFFFF')

for part in [section.header, section.footer]:
    for paragraph in part.paragraphs:
        paragraph.clear()
header = section.header.paragraphs[0]
header.alignment = WD_ALIGN_PARAGRAPH.RIGHT
run = header.add_run('UNIVERSIDAD NACIONAL DE JULIACA · UNAJ')
run.font.size, run.font.color.rgb = Pt(8), RGBColor(0, 0, 0)
footer = section.footer.paragraphs[0]
footer.alignment = WD_ALIGN_PARAGRAPH.CENTER
run = footer.add_run('Propuesta académica | ')
run.font.size = Pt(9)
for instruction, suffix in [('PAGE', ' de '), ('NUMPAGES', '')]:
    field = OxmlElement('w:fldSimple')
    field.set(qn('w:instr'), instruction)
    footer._p.append(field)
    if suffix:
        footer.add_run(suffix).font.size = Pt(9)

source = []
def paragraph(text, bold=False, align=None, size=None):
    p = doc.add_paragraph()
    r = p.add_run(text)
    r.bold = bold
    if size:
        r.font.size = Pt(size)
    if align is not None:
        p.alignment = align
    source.append(text + '\n')
    return p

def heading(text, new_page=False):
    p = doc.add_heading(text, level=1)
    p.paragraph_format.page_break_before = new_page
    source.append('\n## ' + text + '\n')
    return p

def bullet(text):
    p = doc.add_paragraph(style='List Bullet')
    p.add_run(text)
    p.paragraph_format.space_after = Pt(4)
    source.append('- ' + text + '\n')

def table(headers, rows, widths=None):
    t = doc.add_table(rows=1, cols=len(headers))
    t.style = 'Table Grid'
    if widths:
        t.autofit = False
        for col, width in zip(t.columns, widths):
            col.width = Cm(width)
    for cell, text in zip(t.rows[0].cells, headers):
        cell.text = text
        for r in cell.paragraphs[0].runs:
            r.bold = True
    for row in rows:
        for cell, text in zip(t.add_row().cells, row):
            cell.text = text
    header_properties = t.rows[0]._tr.get_or_add_trPr()
    header_properties.append(OxmlElement('w:tblHeader'))
    for row in t.rows:
        row._tr.get_or_add_trPr().append(OxmlElement('w:cantSplit'))
        for cell in row.cells:
            for p in cell.paragraphs:
                p.paragraph_format.space_before = Pt(3)
                p.paragraph_format.space_after = Pt(3)
                p.paragraph_format.line_spacing = 1.06
                p.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.LEFT
                for r in p.runs:
                    r.font.name = 'Times New Roman'
                    r.font.size = Pt(11)
                    r.font.color.rgb = RGBColor(0, 0, 0)
    source.append('| ' + ' | '.join(headers) + ' |\n| ' + ' | '.join(['---'] * len(headers)) + ' |\n')
    source.extend('| ' + ' | '.join(row) + ' |\n' for row in rows)

# Página 1: identidad académica, necesidad y objetivos.
institution = paragraph(
    'UNIVERSIDAD NACIONAL DE JULIACA\n'
    'FACULTAD DE CIENCIAS DE INGENIERÍAS\n'
    'ESCUELA PROFESIONAL DE INGENIERÍA DE SOFTWARE Y SISTEMAS',
    True, WD_ALIGN_PARAGRAPH.CENTER, 11,
)
institution.paragraph_format.keep_with_next = True
logo_paragraph = doc.add_paragraph()
logo_paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
logo_paragraph.paragraph_format.space_before = Pt(3)
logo_paragraph.paragraph_format.space_after = Pt(7)
logo_paragraph.paragraph_format.keep_with_next = True
logo = logo_paragraph.add_run().add_picture(str(OUT / 'recursos' / 'logo-unaj.png'), width=Cm(7.0))
logo._inline.docPr.set('descr', 'Logotipo de la Universidad Nacional de Juliaca (UNAJ)')
source.append('![Logotipo institucional UNAJ](recursos/logo-unaj.png)\n')
title = doc.add_paragraph(style='Title')
title.alignment = WD_ALIGN_PARAGRAPH.CENTER
title.add_run('Propuesta de un sistema web de reservas con chatbot y n8n para el Hotel Golden Roma')
source.append('# Propuesta de un sistema web de reservas con chatbot y n8n para el Hotel Golden Roma\n')
byline = paragraph('Autores:\nDimas Mijael Flores Yerba\nJords Williams Calla Mamani', align=WD_ALIGN_PARAGRAPH.CENTER)
byline.paragraph_format.space_after = Pt(4)
paragraph('Juliaca, 10 de noviembre de 2026', align=WD_ALIGN_PARAGRAPH.CENTER, size=11)
heading('1. Presentación de la propuesta')
paragraph('Se propone al Hotel Golden Roma una página web de reservas con un chatbot: un asistente virtual que conversa con los visitantes y los orienta. Con n8n se conectarán consultas, solicitudes y avisos para reducir tareas manuales. El cliente podrá consultar desde su celular y el personal gestionará las solicitudes en un panel.')
paragraph('El proyecto académico de la Universidad Nacional de Juliaca se adaptará a las necesidades del hotel. Se propone empezar con una demostración y un piloto supervisado para que la administración evalúe su utilidad antes de adoptarlo.')
heading('2. Justificación')
paragraph('Un visitante necesita encontrar con facilidad qué ofrece un hotel, cuánto costaría su estadía y cómo solicitar una reserva. Para el personal, resulta útil reunir las solicitudes en un solo lugar y revisar las fechas con claridad. Una herramienta digital bien utilizada puede facilitar ambas tareas y fortalecer la presentación del negocio.')
heading('3. Objetivos')
paragraph('Objetivo general. Desarrollar y evaluar para el Hotel Golden Roma un sistema de reservas web con chatbot y n8n, que ayude a organizar solicitudes, responder consultas frecuentes y mejorar la atención al huésped.', True)
paragraph('Objetivos específicos:', True)
for text in [
    'Presentar las habitaciones, tarifas y servicios del Hotel Golden Roma con información aprobada por su administración.',
    'Organizar las solicitudes y permitir su confirmación, cancelación y consulta de estado.',
    'Comprobar fechas y capacidad para reducir errores al asignar habitaciones.',
    'Integrar un chatbot con n8n para consultas, avisos y recordatorios, y evaluar su utilidad en un piloto.'
]:
    bullet(text)

# Página 2: beneficios claros para los responsables del hotel.
heading('4. Beneficios esperados para el Hotel Golden Roma', new_page=True)
paragraph('La web presentará la oferta del Hotel Golden Roma, el chatbot orientará al visitante y n8n conectará las tareas de atención. Los beneficios previstos combinan funciones ya desarrolladas y automatizaciones que se integrarán y evaluarán durante el piloto.')
table(['Beneficio', '¿Cómo puede ayudar al establecimiento?'], [
    ('1. Mayor presencia digital', 'Ofrece un espacio propio para presentar el hotel y facilita que los visitantes conozcan su oferta.'),
    ('2. Acceso desde el celular', 'Permite revisar habitaciones y enviar solicitudes sin depender de una computadora.'),
    ('3. Chatbot para consultas frecuentes', 'El asistente virtual podrá explicar habitaciones, servicios y tarifas con datos del sistema, incluso fuera del horario de atención.'),
    ('4. Menos trabajo repetitivo', 'n8n permitirá enlazar tareas: recibir solicitudes, avisar al personal y enviar mensajes según reglas aprobadas.'),
    ('5. Solicitudes mejor organizadas', 'Cada envío quedará registrado; n8n podrá generar un aviso para facilitar su revisión por el personal.'),
    ('6. Menor riesgo de errores', 'Comprueba fechas y capacidad antes de confirmar, para evitar asignaciones incompatibles.'),
    ('7. Precios más comprensibles', 'Muestra un importe estimado según las fechas elegidas y las tarifas registradas.'),
    ('8. Seguimiento oportuno', 'El cliente consultará su solicitud y n8n podrá avisarle cuando el hotel la confirme o cancele.'),
    ('9. Control para la administración', 'El personal autorizado puede actualizar habitaciones, tarifas y servicios desde el mismo entorno.'),
    ('10. Reportes programados', 'n8n podrá preparar resúmenes de solicitudes y ocupación para apoyar la planificación y revisión diaria.'),
    ('11. Recordatorios oportunos', 'Podrán programarse avisos previos a la llegada, respetando el estado de la reserva y los canales acordados.'),
    ('12. Integraciones por etapas', 'Los flujos podrán ampliarse a correo o pasarelas de pago, una vez configurados y probados los servicios.')
], [5.0, 11.0])
paragraph('Una presentación profesional puede generar confianza y favorecer nuevas consultas. La propuesta busca convertir ese interés en solicitudes que el hotel pueda atender y gestionar con mayor orden. El aumento de ventas deberá evaluarse con datos de uso; no se presenta como un resultado garantizado.')

# Página 3: n8n, alcance real, piloto y evaluación.
heading('5. Chatbot y automatización con n8n', new_page=True)
paragraph('El chatbot será la puerta de atención digital: podrá responder sobre habitaciones, servicios y tarifas, consultar disponibilidad y guiar la solicitud de reserva. Obtendrá sus respuestas del sistema del hotel. n8n conectará estas consultas con los procesos internos; el personal autorizado conservará la decisión sobre las confirmaciones y los casos especiales.')
paragraph('Ejemplo: un visitante pregunta por una habitación; el chatbot consulta los datos y lo guía para solicitarla. La web registra el pedido pendiente y n8n avisa al personal. Tras la confirmación del hotel, otro flujo informa al cliente.')
paragraph('La web, el panel y las reservas ya se probaron con datos de demostración. El chatbot y los flujos de n8n siguen pendientes de integración y validación. No se realizan cobros.', size=11)
heading('6. Plan piloto propuesto')
paragraph('Se propone una evaluación de cuatro semanas, ajustable a la disponibilidad del hotel, con participación de la administración.')
table(['Etapa', 'Actividad principal'], [
    ('Semana 1: diagnóstico', 'Revisar la atención actual, acordar contenidos y seleccionar las tareas que se automatizarán.'),
    ('Semana 2: integración', 'Configurar habitaciones, chatbot y flujos de n8n; capacitar al personal y probar avisos.'),
    ('Semana 3: piloto supervisado', 'Realizar solicitudes controladas y evaluar respuestas, notificaciones y seguimiento.'),
    ('Semana 4: evaluación', 'Comparar tiempos, revisar incidencias y acordar mejoras con la administración.')
], [5.0, 11.0])
heading('7. Metas y evaluación de resultados')
for text in [
    'Dar seguimiento a todas las solicitudes del piloto y comprobar el envío de los avisos configurados.',
    'Evitar confirmaciones incompatibles y verificar que el chatbot responda con datos del sistema.',
    'Comparar tiempos de atención y tareas manuales antes y después, y recoger la valoración del personal.'
]:
    bullet(text)
paragraph('Las metas, responsables, canales de comunicación, soporte y costos se acordarán antes de una operación comercial.', size=11)
heading('8. Solicitud de evaluación y colaboración')
paragraph('Solicitamos al Hotel Golden Roma una oportunidad para presentar la demostración y acordar un piloto supervisado. Aportaremos desarrollo, pruebas y capacitación. La administración validará contenidos y resultados antes de decidir la adopción, conservando el control de sus operaciones.')
heading('Referencias técnicas')
for text in [
    'Drupal. (s. f.). Introduction to Form API. https://www.drupal.org/docs/drupal-apis/form-api/introduction-to-form-api',
    'n8n. (s. f.). Understand workflows. https://docs.n8n.io/workflows/'
]:
    p = paragraph(text, size=9)
    p.paragraph_format.space_after = Pt(3)

# Normaliza también el contenido y las partes de encabezado/pie: sin rayas ni colores.
for root in [doc.element, section.header._element, section.footer._element]:
    for border in list(root.iter(qn('w:pBdr'))):
        border.getparent().remove(border)
    for color in root.iter(qn('w:color')):
        color.attrib.clear()
        color.set(qn('w:val'), '000000')
    for shade in root.iter(qn('w:shd')):
        shade.attrib.clear()
        shade.set(qn('w:val'), 'clear')
        shade.set(qn('w:fill'), 'FFFFFF')

props = doc.core_properties
props.title = 'Propuesta de reservas web, chatbot y n8n para el Hotel Golden Roma'
props.subject = 'Objetivos, beneficios y piloto supervisado para el Hotel Golden Roma'
props.author = 'Dimas Mijael Flores Yerba; Jords Williams Calla Mamani'
props.keywords = 'UNAJ, Universidad Nacional de Juliaca, Golden Roma, reservas, chatbot, n8n, automatización'
props.comments = 'Propuesta académica dirigida al Hotel Golden Roma. Beneficios esperados sujetos a evaluación.'
doc.save(TARGET)
(OUT / 'Propuesta_academica_Hostal_ROMA.md').write_text('\n'.join(source), encoding='utf-8')
print(TARGET)
