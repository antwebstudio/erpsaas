import json
import os
import base64
import traceback
from weasyprint import HTML

def lambda_handler(event, context):
    try:
        from weasyprint import HTML
        # Sidecar passes the payload directly into the event object.
        # However, let's also support standard API Gateway / Function URL event payloads.
        if isinstance(event, dict) and 'body' in event:
            try:
                body = json.loads(event.get('body', '{}'))
            except Exception:
                body = event
            html_content = body.get('html', '')
        elif isinstance(event, dict):
            html_content = event.get('html', '')
        else:
            html_content = str(event)

        if not html_content:
            return {
                'statusCode': 400,
                'error': 'No HTML content was provided in the payload.'
            }

        # PDF output destination in Lambda's writeable space
        output_path = '/tmp/output.pdf'
        
        # Compile HTML to PDF using WeasyPrint (uncompressed for FPDI compatibility)
        HTML(string=html_content).write_pdf(output_path, uncompressed_pdf=True)
        
        # Read the compiled PDF and base64-encode it
        with open(output_path, 'rb') as f:
            pdf_data = f.read()
            
        pdf_base64 = base64.b64encode(pdf_data).decode('utf-8')
        
        return {
            'statusCode': 200,
            'body': pdf_base64
        }
    except Exception as e:
        return {
            'statusCode': 500,
            'error': str(e) + "\n" + traceback.format_exc()
        }
