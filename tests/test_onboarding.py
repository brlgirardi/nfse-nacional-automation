import json, os, pathlib, shutil, subprocess, sys, tempfile, unittest
ROOT=pathlib.Path(__file__).resolve().parents[1]

class Onboarding(unittest.TestCase):
 def test_private_wizard_and_default_description(self):
  with tempfile.TemporaryDirectory() as work:
   temp=pathlib.Path(work)
   (temp/'config').mkdir()
   (temp/'scripts').mkdir()
   shutil.copyfile(ROOT/'config/modelo-fiscal.example.json',temp/'config/modelo-fiscal.example.json')
   for f in ('setup.py','new-request.py','preflight.php'):
    shutil.copyfile(ROOT/'scripts'/f,temp/'scripts'/f)
   answers=['12345678000199','EMPRESA DE EXEMPLO LTDA','',
    '3550308','3550308','','','3','1','0','010101',
    'Classificacao de exemplo','','','1','nao','6',
    '10001','Descricao padrao ficticia','nao']
   proc=subprocess.run([sys.executable,'scripts/setup.py'],input='\n'.join(answers)+'\n',
    text=True,capture_output=True,cwd=temp)
   self.assertEqual(proc.returncode,0,proc.stderr+' '+proc.stdout[-300:])
   config=temp/'config/modelo-fiscal.json'
   self.assertEqual(os.stat(config).st_mode & 0o777,0o600)
   c=json.loads(config.read_text())
   self.assertFalse(c['defaults']['confirmEachInvoice'])
   values=['emitir','homologation','TESTE','12345678901','Tomador Ficticio',
    '1.00','2026-11-03','S']
   proc=subprocess.run([sys.executable,'scripts/new-request.py'],input='\n'.join(values)+'\n',
    text=True,capture_output=True,cwd=temp)
   self.assertEqual(proc.returncode,0,proc.stderr+' '+proc.stdout[-300:])
   requests=list((temp/'requests').glob('*.json'))
   self.assertEqual(len(requests),1)
   request=json.loads(requests[0].read_text())
   self.assertNotIn('descricaoServico',request)
   r=subprocess.run(['php','scripts/preflight.php',str(requests[0])],
    text=True,capture_output=True,cwd=temp)
   self.assertEqual(r.returncode,0,r.stderr)
