# eSkill NLP Microservice

Este microserviço Python (FastAPI) é o motor de Machine Learning responsável por ler perguntas e mensagens do Mercado Livre e classificar a **Intenção** e a **Urgência/Sentimento** dos clientes (Smart SAC).

Ele faz parte do ecossistema eSkill e é consumido pelo backend em PHP.

## Estrutura de Diretórios

```text
ml-nlp-service/
├── app/
│   ├── api/            # Controladores e rotas FastAPI
│   ├── core/           # Configurações e segurança
│   ├── models/         # Definições Pydantic e modelos ML salvos (.pkl)
│   ├── services/       # Lógica de predição e feature engineering
│   └── main.py         # Ponto de entrada do app
├── tests/              # Testes (pytest)
├── .env.example        # Variáveis de ambiente de exemplo
├── requirements.txt    # Dependências do Python
├── package.json        # Ferramentas auxiliares (opcional)
└── tsconfig.json       # Ferramentas auxiliares (opcional)
```

## Requisitos

- Python 3.10+
- `pip` ou `uv` para gerenciamento de pacotes.

## Instalação e Execução (Desenvolvimento)

1. **Crie um ambiente virtual (recomendado):**
   ```bash
   python -m venv venv
   source venv/bin/activate
   ```

2. **Instale as dependências:**
   ```bash
   pip install -r requirements.txt
   ```

3. **Configure o ambiente:**
   ```bash
   cp .env.example .env
   # Edite o .env se necessário (ex: API_KEY)
   ```

4. **Inicie o servidor de desenvolvimento:**
   ```bash
   uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
   ```

5. **Teste a API:**
   - Documentação interativa (Swagger UI): [http://localhost:8000/docs](http://localhost:8000/docs)
   - Health Check: [http://localhost:8000/health](http://localhost:8000/health)

### Exemplo de Requisição (Predict)

```bash
curl -X POST http://localhost:8000/api/v1/predict \
  -H "Content-Type: application/json" \
  -H "X-API-Key: dev-secret-key" \
  -d '{
    "message_id": "MSG-12345",
    "text": "Minha peça veio quebrada, vou no procon!",
    "item_id": "MLB1234567",
    "price": 150.00
  }'
```
