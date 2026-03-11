#!/usr/bin/env python3
"""
Script OCR simples para extrair texto de imagens.
Usa Tesseract via pytesseract, ou PIL + pytesseract.
"""

import sys
import json
import os

def extract_text_ocr(image_path):
    """Extrai texto da imagem usando Tesseract OCR"""
    try:
        # Tenta usar pytesseract (requer tesseract instalado no sistema)
        import pytesseract
        from PIL import Image
        
        img = Image.open(image_path)
        text = pytesseract.image_to_string(img, lang='por')
        
        return {
            'status': 'success',
            'text': text.strip() if text else '',
            'method': 'tesseract'
        }
    except ImportError:
        # Se pytesseract não está instalado, tenta método alternativo
        try:
            from PIL import Image
            import subprocess
            
            # Tenta chamar tesseract diretamente
            result = subprocess.run(
                ['tesseract', image_path, 'stdout', '-l', 'por'],
                capture_output=True,
                text=True
            )
            
            if result.returncode == 0:
                return {
                    'status': 'success',
                    'text': result.stdout.strip(),
                    'method': 'tesseract_direct'
                }
        except Exception:
            pass
        
        return {
            'status': 'error',
            'text': '',
            'error': 'Tesseract não encontrado. Instala: pip install pytesseract'
        }
    except Exception as e:
        return {
            'status': 'error',
            'text': '',
            'error': str(e)
        }

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({'status': 'error', 'error': 'Caminho da imagem não fornecido'}))
        sys.exit(1)
    
    image_path = sys.argv[1]
    result = extract_text_ocr(image_path)
    print(json.dumps(result, ensure_ascii=False))
