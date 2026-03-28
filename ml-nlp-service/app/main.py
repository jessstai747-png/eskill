from fastapi import FastAPI, Depends, HTTPException, Security
from fastapi.security import APIKeyHeader
from pydantic import BaseModel, Field
import os
import uvicorn

from app.services.nlp_pipeline import nlp_service

# Configuração simples via Env
API_KEY = os.getenv("API_KEY", "dev-secret-key")
api_key_header = APIKeyHeader(name="X-API-Key", auto_error=False)

app = FastAPI(
    title="eSkill NLP Microservice",
    description="Motor ML de Triagem e Classificação de Atendimento (Smart SAC)",
    version="1.0.0"
)

# Dependência de Autenticação
async def get_api_key(api_key: str = Security(api_key_header)):
    if api_key != API_KEY and API_KEY != "dev-secret-key":
        raise HTTPException(status_code=403, detail="Could not validate credentials")
    return api_key

# Schemas
class MessageRequest(BaseModel):
    message_id: str = Field(..., description="ID único da mensagem no ML")
    text: str = Field(..., description="Texto da pergunta/mensagem original")
    item_id: str = Field(..., description="ID do item associado (MLB...)")
    price: float = Field(..., description="Preço atual do item")

class MessageResponse(BaseModel):
    message_id: str
    intent: str
    urgency_score: float
    confidence: float
    is_critical: bool

@app.get("/health")
async def health_check():
    """Verifica se a API e os modelos estão carregados e operacionais."""
    return {"status": "ok", "model_loaded": nlp_service.is_loaded, "version": "1.0.0"}

@app.post("/api/v1/predict", response_model=MessageResponse)
async def predict_intent(request: MessageRequest, api_key: str = Depends(get_api_key)):
    """
    Recebe uma mensagem do ML e retorna a classificação de intenção e urgência.
    Utiliza o modelo SVM + TF-IDF treinado pelo nlp_pipeline.
    """
    try:
        # Inferencia real usando o modelo
        prediction = nlp_service.predict(request.text)
        
        return MessageResponse(
            message_id=request.message_id,
            intent=prediction["intent"],
            urgency_score=prediction["urgency_score"],
            confidence=prediction["confidence"],
            is_critical=prediction["urgency_score"] >= 0.8
        )
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erro na inferencia do modelo: {str(e)}")

if __name__ == "__main__":
    uvicorn.run("app.main:app", host="0.0.0.0", port=8000, reload=True)
