#!/usr/bin/env python3
"""
Script de validação de produção para eskill.com.br
Testa página de login e rotas do dashboard usando requests
"""

import requests
import sys
import os
import re
from bs4 import BeautifulSoup
from datetime import datetime
from typing import Dict, List, Tuple

# Configurações
PROD_URL = "https://eskill.com.br"
USER_AGENT = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0.0.0"

# Cores para terminal
class Colors:
    GREEN = '\033[92m'
    YELLOW = '\033[93m'
    RED = '\033[91m'
    BLUE = '\033[94m'
    BOLD = '\033[1m'
    END = '\033[0m'

def print_header(text: str):
    """Imprime cabeçalho colorido"""
    print(f"\n{Colors.BLUE}{Colors.BOLD}{'='*60}{Colors.END}")
    print(f"{Colors.BLUE}{Colors.BOLD}{text.center(60)}{Colors.END}")
    print(f"{Colors.BLUE}{Colors.BOLD}{'='*60}{Colors.END}\n")

def print_success(text: str):
    """Imprime mensagem de sucesso"""
    print(f"{Colors.GREEN}✓{Colors.END} {text}")

def print_warning(text: str):
    """Imprime mensagem de aviso"""
    print(f"{Colors.YELLOW}⚠{Colors.END} {text}")

def print_error(text: str):
    """Imprime mensagem de erro"""
    print(f"{Colors.RED}✗{Colors.END} {text}")

def inspect_login_page() -> Tuple[requests.Session, Dict]:
    """
    Inspeção da página de login
    Retorna sessão e informações de CSRF
    """
    print_header("1. INSPEÇÃO DA PÁGINA DE LOGIN")

    session = requests.Session()
    session.headers.update({
        'User-Agent': USER_AGENT,
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language': 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
    })

    try:
        response = session.get(f"{PROD_URL}/login", timeout=10)
        print(f"Status HTTP: {response.status_code}")

        if response.status_code != 200:
            print_error(f"Erro ao acessar página de login: {response.status_code}")
            return session, {}

        print_success("Página de login acessível")

        # Parse HTML
        soup = BeautifulSoup(response.text, 'html.parser')

        # 1. Verificar campos de email/password
        email_field = soup.find('input', {'name': 'email'}) or soup.find('input', {'type': 'email'})
        password_field = soup.find('input', {'name': 'password'}) or soup.find('input', {'type': 'password'})

        if email_field:
            print_success("Campo de email encontrado")
        else:
            print_warning("Campo de email NÃO encontrado")

        if password_field:
            print_success("Campo de password encontrado")
        else:
            print_warning("Campo de password NÃO encontrado")

        # 2. Verificar hidden _token
        csrf_input = soup.find('input', {'name': '_token'})
        csrf_token = csrf_input.get('value') if csrf_input else None

        if csrf_token:
            print_success(f"CSRF Token (input hidden): {csrf_token[:20]}...")
        else:
            print_warning("CSRF Token (input hidden) NÃO encontrado")

        # 3. Verificar meta csrf-token
        csrf_meta = soup.find('meta', {'name': 'csrf-token'})
        csrf_meta_content = csrf_meta.get('content') if csrf_meta else None

        if csrf_meta_content:
            print_success(f"CSRF Token (meta tag): {csrf_meta_content[:20]}...")
        else:
            print_warning("CSRF Token (meta tag) NÃO encontrado")

        # 4. Verificar cookies de sessão
        cookies = session.cookies.get_dict()
        session_cookie = cookies.get('PHPSESSID') or cookies.get('sessionid')

        if session_cookie or cookies:
            print_success(f"Cookies recebidos: {len(cookies)}")
            if session_cookie:
                print_success(f"Cookie de sessão: PHPSESSID={session_cookie[:20]}...")
            else:
                print_warning(f"Cookies: {list(cookies.keys())}")
        else:
            print_warning("Nenhum cookie de sessão encontrado")

        return session, {
            'csrf_token': csrf_token or csrf_meta_content,
            'has_email_field': bool(email_field),
            'has_password_field': bool(password_field),
            'has_csrf': bool(csrf_token or csrf_meta_content),
            'cookies': cookies
        }

    except requests.RequestException as e:
        print_error(f"Erro ao acessar página de login: {e}")
        return session, {}

def test_login(session: requests.Session, csrf_token: str, email: str, password: str) -> bool:
    """
    Testa login com credenciais reais
    Retorna True se login bem-sucedido
    """
    print_header("2. TESTE DE LOGIN COM CREDENCIAIS")

    if not email or not password:
        print_warning("Credenciais não fornecidas - teste de login pulado")
        print("   Execute: python3 prod-validation.py <email> <password>")
        return False

    try:
        # Preparar dados do formulário
        login_data = {
            'email': email,
            'password': password,
        }

        if csrf_token:
            login_data['_token'] = csrf_token

        # Tentar login via POST
        print(f"Tentando login como: {email}")
        response = session.post(
            f"{PROD_URL}/login",
            data=login_data,
            allow_redirects=True,
            timeout=10
        )

        print(f"Status HTTP: {response.status_code}")
        print(f"URL final: {response.url}")

        # Verificar se redirecionou para dashboard
        if 'dashboard' in response.url:
            print_success("Login bem-sucedido! Redirecionado para dashboard")
            return True

        # Verificar se há mensagem de erro
        soup = BeautifulSoup(response.text, 'html.parser')
        error_elements = soup.find_all(class_=['alert-danger', 'error', 'text-danger'])

        if error_elements:
            error_text = error_elements[0].get_text(strip=True)
            print_error(f"Erro de login: {error_text}")
        else:
            print_warning("Login pode ter falhado (não redirecionou para dashboard)")

        # Tentar via API como diagnóstico
        print("\n   Tentando via API /api/auth/login...")
        api_response = session.post(
            f"{PROD_URL}/api/auth/login",
            json={'email': email, 'password': password},
            headers={'Accept': 'application/json'},
            timeout=10
        )

        print(f"   API Status: {api_response.status_code}")
        print(f"   API Response: {api_response.text[:200]}")

        if api_response.status_code == 200:
            print_success("API funciona - problema pode ser de CSRF/sessão no fluxo web")
        else:
            print_error("API falhou - problema nas credenciais ou lógica de autenticação")

        return False

    except requests.RequestException as e:
        print_error(f"Erro ao tentar login: {e}")
        return False

def test_dashboard_routes(session: requests.Session):
    """
    Smoke test de todas as rotas do dashboard
    """
    print_header("3. SMOKE TEST DAS ROTAS DO DASHBOARD")

    routes = [
        ('/dashboard', 'Dashboard Principal'),
        ('/dashboard/accounts', 'Contas'),
        ('/dashboard/analytics', 'Analytics'),
        ('/dashboard/account-health', 'Account Health'),
        ('/dashboard/items', 'Items'),
        ('/dashboard/orders', 'Orders'),
        ('/dashboard/questions', 'Questions'),
        ('/dashboard/messages', 'Messages'),
        ('/dashboard/claims', 'Claims'),
        ('/dashboard/seo-killer', 'SEO Killer'),
        ('/dashboard/financials', 'Financials'),
        ('/dashboard/pricing', 'Pricing'),
    ]

    success_count = 0
    fail_count = 0
    results = []

    for route, name in routes:
        try:
            response = session.get(f"{PROD_URL}{route}", timeout=10, allow_redirects=True)
            status = response.status_code

            if 200 <= status < 400:
                print_success(f"[{status}] {name}")
                success_count += 1
                results.append((name, status, True))
            else:
                print_error(f"[{status}] {name}")
                fail_count += 1
                results.append((name, status, False))

            # Verificar se redirecionou para login (sessão expirada)
            if 'login' in response.url:
                print_warning(f"      Redirecionado para login - possível sessão expirada")

        except requests.RequestException as e:
            print_error(f"[ERROR] {name}: {e}")
            fail_count += 1
            results.append((name, 0, False))

    print(f"\n{Colors.BOLD}Resumo:{Colors.END} {success_count} sucessos, {fail_count} falhas")
    return results

def main():
    """Função principal"""
    print_header("🧪 VALIDAÇÃO DE PRODUÇÃO - eskill.com.br")

    # Obter credenciais dos argumentos ou env vars
    email = sys.argv[1] if len(sys.argv) > 1 else os.getenv('PROD_EMAIL')
    password = sys.argv[2] if len(sys.argv) > 2 else os.getenv('PROD_PASSWORD')

    # 1. Inspecionar página de login
    session, login_info = inspect_login_page()

    if not login_info:
        print_error("Falha ao inspecionar página de login")
        return 1

    # 2. Testar login (se credenciais fornecidas)
    logged_in = False
    if email and password:
        logged_in = test_login(session, login_info.get('csrf_token'), email, password)
    else:
        print_header("2. TESTE DE LOGIN COM CREDENCIAIS")
        print_warning("Credenciais não fornecidas - teste de login pulado")
        print("   Execute: python3 prod-validation.py <email> <password>")

    # 3. Smoke test das rotas (se logado)
    if logged_in:
        test_dashboard_routes(session)
    else:
        print_header("3. SMOKE TEST DAS ROTAS DO DASHBOARD")
        print_warning("Smoke test não executado (sem sessão autenticada)")

    # 4. Resumo final
    print_header("📋 RESUMO FINAL")
    print(f"URL testada: {PROD_URL}")
    print(f"Página de login: {'OK' if login_info else 'FALHOU'}")
    print(f"CSRF Token: {'OK' if login_info.get('has_csrf') else 'FALTANDO'}")
    print(f"Campos de formulário: {'OK' if login_info.get('has_email_field') and login_info.get('has_password_field') else 'INCOMPLETO'}")
    print(f"Login: {'SUCESSO' if logged_in else 'Não testado ou falhou'}")

    print(f"\n{Colors.YELLOW}💡 Para teste completo com browser automation:{Colors.END}")
    print(f"   ./run-prod-validation.sh {email or '<email>'} {password or '<password>'}")
    print("")

    return 0 if login_info else 1

if __name__ == '__main__':
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        print(f"\n{Colors.YELLOW}⚠ Interrompido pelo usuário{Colors.END}")
        sys.exit(130)
    except Exception as e:
        print(f"\n{Colors.RED}✗ Erro inesperado: {e}{Colors.END}")
        import traceback
        traceback.print_exc()
        sys.exit(1)
