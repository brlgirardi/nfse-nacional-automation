#!/usr/bin/env python3
"""Gera solicitação privada em requests/. Nunca transmite automaticamente."""
import json, os, secrets, datetime
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def ask(label,default=""):
    while True:
        v=input(label+(f" [{default}]" if default else "")+": ").strip() or default
        if v:return v
        print("Obrigatório.")
def create():
    print("NFS-e Nacional Automation — Bruno Liberato Girardi")
    op=ask("Operação (emitir/cancelar)").lower()
    if op not in ("emitir","cancelar"):raise SystemExit("Operação inválida.")
    env=ask("Ambiente (homologation/production)","homologation").lower()
    if env not in ("homologation","production"):raise SystemExit("Ambiente inválido.")
    r={"operacao":op,"ambiente":env,"confirmarProducao":False}
    if op=="emitir":
        r["identificador"]=ask("Identificador do serviço (não incluir dados pessoais)","SERVICO").upper()
        r["tomadorDocumento"]=ask("CPF/CNPJ tomador")
        r["tomadorNome"]=ask("Nome/razão social tomador")
        if len("".join(c for c in r["tomadorDocumento"] if c.isdigit()))==14:
            for k,label in [("tomadorMunicipioIbge","Município IBGE tomador"),("tomadorCep","CEP tomador"),("tomadorLogradouro","Logradouro"),("tomadorNumero","Número"),("tomadorBairro","Bairro")]:r[k]=ask(label)
            r["tomadorComplemento"]=input("Complemento (opcional): ").strip()
        while True:
            try:
                v=float(ask("Valor em reais (ex. 1250.50)").replace(",","."))
                if v>=.01 and round(v,2)==v:break
            except ValueError:pass
            print("Valor positivo, até duas casas decimais.")
        r["valor"]=v
        r["competencia"]=ask("Competência AAAA-MM-DD",datetime.date.today().isoformat())
        # A política de descrição é individual; nunca fica nos arquivos de exemplo.
        try:
            settings=json.loads((ROOT/"config/modelo-fiscal.json").read_text()).get("defaults",{})
        except (FileNotFoundError,ValueError):
            settings={}
        default_desc=settings.get("serviceDescription","")
        confirm_each=settings.get("confirmEachInvoice",True)
        if confirm_each or not default_desc:
            r["descricaoServico"]=ask("Descrição exata do serviço (revisada)",default_desc)
        else:
            response=input("Usar descrição padrão previamente validada nesta nota? (S/N): ").strip().casefold()
            if response not in ("s","sim"):
                r["descricaoServico"]=ask("Nova descrição do serviço (revisada)")
            else:
                print("Descrição padrão será usada da configuração privada.")

        r["numeroDps"]=datetime.datetime.now().strftime("%y%m%d%H%M%S")+f"{secrets.randbelow(1000):03d}"
        print("Número DPS persistido; não altere em uma tentativa repetida.")
    else:
        r["chaveAcesso"]=ask("Chave NFS-e (50 dígitos)")
        r["codigoMotivo"]=ask("Motivo (1 erro, 2 serviço não prestado, 9 outros)")
        r["motivo"]=ask("Motivo fundamentado")
    if env=="production":
        print("PRODUÇÃO: documento fiscal real. Valide legalidade, valor, descrição e cancelamento.")
        r["confirmarProducao"]=input("Revisou os dados e quer preparar a operação? Digite SIM: ").strip()=="SIM"
    out=ROOT/"requests";out.mkdir(parents=True,exist_ok=True,mode=0o700)
    p=out/(op+"-"+datetime.datetime.now().strftime("%Y%m%d-%H%M%S")+"-"+secrets.token_hex(3)+".json")
    fd=os.open(p,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600)
    with os.fdopen(fd,"w",encoding="utf-8") as f:json.dump(r,f,ensure_ascii=False,indent=2);f.write("\n")
    print("Arquivo privado:",p.relative_to(ROOT))
    print("Verifique com: php scripts/preflight.php",p.relative_to(ROOT))
if __name__=="__main__":create()
