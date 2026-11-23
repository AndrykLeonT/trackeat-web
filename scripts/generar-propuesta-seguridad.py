"""Generate the agreed editable document; render it with the bundled DOCX renderer."""
from pathlib import Path
import re
from docx import Document
from docx.shared import Inches, Pt, RGBColor
from docx.oxml import OxmlElement
from docx.oxml.ns import qn

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'output' / 'seguridad'
OUT.mkdir(parents=True, exist_ok=True)
source = (ROOT / 'docs' / 'propuesta-seguridad.md').read_text(encoding='utf-8')
pages = source.split('<!-- page -->')
assert len(pages) == 8
doc = Document()
section = doc.sections[0]
section.page_width, section.page_height = Inches(8.5), Inches(11)
section.top_margin = section.bottom_margin = Inches(0.72)
section.left_margin = section.right_margin = Inches(0.85)
section.header_distance = section.footer_distance = Inches(0.30)
styles = doc.styles
for name in ['Normal', 'Title', 'Subtitle', 'Heading 1', 'Heading 2']:
    styles[name].font.name = 'Calibri'
    styles[name].font.color.rgb = RGBColor(0, 0, 0)
styles['Normal'].font.size = Pt(11.5)
styles['Normal'].paragraph_format.line_spacing = 1.1
styles['Normal'].paragraph_format.space_after = Pt(8)
styles['Title'].font.size = Pt(27)
styles['Title'].font.bold = True
styles['Title'].paragraph_format.space_after = Pt(24)
styles['Heading 1'].font.size = Pt(19)
styles['Heading 1'].paragraph_format.space_after = Pt(15)
styles['Heading 1'].paragraph_format.space_before = Pt(0)
for name in ['Title', 'Heading 1', 'Heading 2']:
    styles[name].paragraph_format.keep_with_next = True

header = section.header.paragraphs[0]
header.text = 'TRACKEAT  |  PROPUESTA DE SEGURIDAD'
header.style = styles['Normal']
header.runs[0].font.size = Pt(9)
footer = section.footer.paragraphs[0]
footer.alignment = 2
footer.add_run('TrackEat   ·   ')
field = OxmlElement('w:fldSimple')
field.set(qn('w:instr'), 'PAGE')
footer._p.append(field)
for run in footer.runs:
    run.font.size = Pt(9)

def add_table(lines):
    rows = [[cell.strip() for cell in line.strip().strip('|').split('|')] for line in lines]
    rows = [row for row in rows if not all(re.fullmatch(r'[-: ]+', cell) for cell in row)]
    table = doc.add_table(rows=0, cols=3)
    table.autofit = False
    for column, width in zip(table.columns, [1.65, 3.73, 1.42]):
        column.width = Inches(width)
    for index, values in enumerate(rows):
        row = table.add_row()
        for cell, value, width in zip(row.cells, values, [1.65, 3.73, 1.42]):
            cell.width = Inches(width)
            cell.vertical_alignment = 1
            cell.text = value
            props = cell._tc.get_or_add_tcPr()
            borders = OxmlElement('w:tcBorders')
            for side in ['top','left','bottom','right']:
                element = OxmlElement(f'w:{side}')
                element.set(qn('w:val'), 'single')
                element.set(qn('w:sz'), '4')
                element.set(qn('w:color'), 'D9D9D9')
                borders.append(element)
            props.append(borders)
            shade = OxmlElement('w:shd')
            shade.set(qn('w:fill'), '334155' if index == 0 else ('F2F5F7' if index % 2 else 'FFFFFF'))
            props.append(shade)
            margin = OxmlElement('w:tcMar')
            for side in ['top','left','bottom','right']:
                element = OxmlElement(f'w:{side}')
                element.set(qn('w:w'), '80')
                element.set(qn('w:type'), 'dxa')
                margin.append(element)
            props.append(margin)
            for paragraph in cell.paragraphs:
                paragraph.paragraph_format.space_after = Pt(1)
                paragraph.paragraph_format.line_spacing = 1.02
                for run in paragraph.runs:
                    run.font.size = Pt(9)
                    run.bold = index == 0
                    run.font.color.rgb = RGBColor(255,255,255) if index == 0 else RGBColor(0,0,0)
        prevent_split = OxmlElement('w:cantSplit')
        row._tr.get_or_add_trPr().append(prevent_split)
        if index == 0:
            repeat = OxmlElement('w:tblHeader')
            row._tr.get_or_add_trPr().append(repeat)
    doc.add_paragraph().paragraph_format.space_after = Pt(0)

for index, page in enumerate(pages):
    if index: doc.add_page_break()
    blocks = re.split(r'\n\s*\n', page.strip())
    for block in blocks:
        if block.startswith('|'):
            add_table(block.splitlines())
        elif block.startswith('# '):
            paragraph = doc.add_paragraph(block[2:], 'Title')
            paragraph.paragraph_format.space_before = Pt(40)
        elif block.startswith('## '):
            doc.add_paragraph(block[3:], 'Heading 1')
        else:
            paragraph = doc.add_paragraph(block.replace('\n', ' '))
            if index == 0:
                paragraph.paragraph_format.space_after = Pt(10)
            elif index == 7:
                paragraph.paragraph_format.space_after = Pt(12)
                for run in paragraph.runs: run.font.size = Pt(10.5)
    if index == 0:
        for paragraph in doc.paragraphs:
            paragraph.alignment = 1

doc.core_properties.title = 'Propuesta integral de seguridad de TrackEat'
doc.core_properties.author = 'Equipo TrackEat'
doc.core_properties.subject = 'Aplicaciones Web II Tarea 5'
# The managed default template can carry a blue bottom border on Title.
for root in [doc.element, doc.styles.element]:
    for border in root.xpath('.//w:pBdr'):
        border.getparent().remove(border)
doc.save(OUT / 'TrackEat_propuesta_seguridad.docx')
print(OUT / 'TrackEat_propuesta_seguridad.docx')
