"""Render and inspect the native Word PDF with the bundled pdfplumber runtime."""
from pathlib import Path
import pdfplumber
from PIL import Image, ImageDraw

root = Path(__file__).resolve().parents[1]
output = root / 'tmp' / 'propuesta-render'
output.mkdir(parents=True, exist_ok=True)
thumbnails = []
with pdfplumber.open(root / 'output/seguridad/TrackEat_propuesta_seguridad.pdf') as pdf:
    assert len(pdf.pages) == 8, f'Expected 8 pages, got {len(pdf.pages)}'
    for i, page in enumerate(pdf.pages):
        text = page.extract_text()
        assert text and '\ufffd' not in text, f'Invalid text on page {i + 1}'
        page.to_image(resolution=110).save(output / f'page-{i+1}.png')
        print(f'Page {i+1}: {len(text.split())} words')
        thumb = Image.open(output / f'page-{i+1}.png').convert('RGB')
        thumb.thumbnail((425, 550))
        tile = Image.new('RGB', (445, 585), '#e8e8e8')
        tile.paste(thumb, ((445 - thumb.width) // 2, 10))
        ImageDraw.Draw(tile).text((15, 565), f'Page {i+1}', fill='black')
        thumbnails.append(tile)
contact = Image.new('RGB', (1780, 1170), 'white')
for i, tile in enumerate(thumbnails):
    contact.paste(tile, ((i % 4) * 445, (i // 4) * 585))
contact.save(output / 'overview.png')
