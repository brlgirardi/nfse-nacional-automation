import json,pathlib,unittest
ROOT=pathlib.Path(__file__).resolve().parents[1]
class Tests(unittest.TestCase):
 def test_example_no_identity(self):
  x=json.loads((ROOT/'config/modelo-fiscal.example.json').read_text())
  self.assertEqual(x['prestador']['cnpj'],'')
  self.assertIsNone(x['prestador']['regimeTributario']['opSimpNac'])
  self.assertIsNone(x['servico']['issRetido'])
  self.assertTrue(x['defaults']['confirmEachInvoice'])
  self.assertEqual(x['defaults']['serviceDescription'],'')
 def test_gitignore(self):
  s=(ROOT/'.gitignore').read_text()
  for v in ('/config/modelo-fiscal.json','*.pfx','*.p12','/requests/','/output/'):self.assertIn(v,s)
 def test_workflow_only_manual(self):
  s=(ROOT/'.github/workflows/nfse-manual.yml').read_text()
  self.assertIn('workflow_dispatch:',s)
  self.assertNotIn('issues:\n',s)
 def test_generic_fiscal_identifiers(self):
  s=''.join(p.read_text() for p in (ROOT/'src').glob('*.php'))
  self.assertNotIn('in_array($empresaSlug',s)
  self.assertIn('identificador',s)
if __name__=='__main__':unittest.main()
