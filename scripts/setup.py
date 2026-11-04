#!/usr/bin/env python3
"""Wizard local: dados fiscais ficam SOMENTE no arquivo ignorado pelo Git."""
from __future__ import annotations
import json, os
from pathlib import Path
BASE = Path(__file__).resolve().parents[1]
TARGET = BASE / "config" / "modelo-fiscal.json"
SAMPLE = BASE / "config" / "modelo-fiscal.example.json"

def ask(question: str, required: bool = True, default: str = "") -> str:
    while True:
        v = input(f"{question}{f' [{default}]' if default else ''}: ").strip() or default
        if v or not required:
            return v
        print("Campo obrigatório. Confirme com sua contabilidade quando necessário.")

def num(question: str, allowed: set[int] | None = None) -> int:
    while True:
        try:
            v = int(ask(question))
            if allowed is not None and v not in allowed: raise ValueError
            return v
        except ValueError:
            print("Valor numérico não permitido. Confira a documentação fiscal.")

def yesno(question: str) -> bool:
    while True:
        v = ask(question+" (sim/não)").casefold()
        if v in ("s","sim","y","yes"): return True
        if v in ("n","não","nao","no"): return False
        print("Responda sim ou não.")

def percent(question: str) -> float:
    while True:
        try:
            v=float(ask(question).replace(",","."))
            if 0 <= v <= 100: return v
        except ValueError: pass
        print("Informe percentual de 0 a 100, validado pela contabilidade.")

def main() -> None:
    if TARGET.exists():
        print("Configuração local já existe. Não sobrescrevi. Faça backup seguro antes de alterar.")
        return
    data = json.loads(SAMPLE.read_text(encoding="utf-8"))
    p,s,d=data["prestador"],data["servico"],data["dps"]
    print("\\n" + "=" * 60)
    print("  BEM-VINDO AO NFS-e NACIONAL AUTOMATION")
    print("  Configuração individual e segura do seu emissor")
    print("=" * 60)
    print("Este assistente adapta o cadastro fiscal e a rotina ao prestador.")
    print("Dados reais serão salvos apenas em config/modelo-fiscal.json (ignorado pelo Git).")
    print("Nunca informe senhas ou certificados neste formulário.\\n")
    print("Responda somente com dados confirmados. NUNCA cole senha/certificado aqui.")
    p["cnpj"]=ask("CNPJ do prestador (14 dígitos)")
    p["nome"]=ask("Razão social")
    p["inscricaoMunicipal"]=ask("Inscrição municipal (opcional)",False)
    p["municipioEmissaoIbge"]=ask("Código IBGE município de emissão (7 dígitos)")
    p["municipioPrestacaoIbge"]=ask("Código IBGE município de prestação (7 dígitos)")
    p["telefone"]=ask("Telefone cadastral (opcional)",False)
    p["email"]=ask("E-mail fiscal (opcional)",False)
    print("\nTributação: confirme com seu contador.")
    r=p["regimeTributario"]
    r["opSimpNac"]=num("opSimpNac (1=não optante, 2=MEI, 3=ME/EPP Simples)",{1,2,3})
    r["regApTribSN"]=num("regApTribSN (1,2 ou 3)",{1,2,3}) if r["opSimpNac"]==3 else None
    r["regEspTrib"]=num("regEspTrib conforme enquadramento")
    s["codigoTributacaoNacional"]=ask("Código tributação nacional (6 dígitos)")
    s["descricaoTributacaoNacional"]=ask("Descrição oficial da classificação")
    s["codigoNbs"]=ask("Código NBS (9 dígitos, vazio quando não aplicável)",False)
    s["descricaoNbs"]=ask("Descrição NBS (opcional)",False)
    s["tribISSQN"]=num("Código tribISSQN conforme classificação")
    s["issRetido"]=yesno("ISS retido pelo tomador?")
    s["percentualTotalTributosSN"]=percent("Percentual total tributos Simples (0 se orientado)") if r["opSimpNac"]==3 else None
    d["serieAutomacao"]=ask("Série própria da DPS (sem conflito com emissor web)", default="10001")
    d["ambientePadrao"]="homologation"
    default_desc=ask("Descrição padrão do serviço (opcional)",False)
    confirm_each=yesno("Confirmar a descrição em CADA NFS-e?")
    if not confirm_each and not default_desc:
        raise SystemExit("Para desabilitar confirmação por nota é obrigatório salvar descrição padrão validada.")
    data["defaults"]={"serviceDescription":default_desc,"confirmEachInvoice":confirm_each}
    TARGET.parent.mkdir(parents=True,exist_ok=True)
    fd=os.open(TARGET,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,"w",encoding="utf-8") as f:
        json.dump(data,f,ensure_ascii=False,indent=2);f.write("\n")
    print("Criado config/modelo-fiscal.json local (0600; ignorado pelo Git).")
    print("Próximos passos: php scripts/preflight.php e configure o A1 fora do chat.")
if __name__=="__main__":main()
