import re
import string
import joblib
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.svm import SVC
from sklearn.pipeline import Pipeline
import os

# Configurações de caminhos
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
MODELS_DIR = os.path.join(BASE_DIR, "app", "models", "saved")

os.makedirs(MODELS_DIR, exist_ok=True)

MODEL_PATH = os.path.join(MODELS_DIR, "svm_intent_model.pkl")

class NLPModelService:
    def __init__(self):
        self.model = None
        self.is_loaded = False
        self._load_model()

    def _clean_text(self, text: str) -> str:
        """Limpeza básica de texto para NLP e-commerce (Mercado Livre)"""
        text = text.lower()
        # Remove pontuações e caracteres especiais mantendo números e letras
        text = re.sub(f'[{re.escape(string.punctuation)}]', ' ', text)
        # Remove espaços extras
        text = re.sub(r'\s+', ' ', text).strip()
        return text

    def train_initial_model(self):
        """Treina um modelo baseline (SVM + TF-IDF) com dados sintéticos"""
        print("Iniciando treinamento do modelo base...")
        
        # Dataset mockado baseado no cenário de peças de moto/Mercado Livre
        data = {
            "text": [
                "Serve na cg titan 160 2018?",
                "da certo na fan 125 2010",
                "boa tarde, tem pra biz 150?",
                "qual a compatibilidade dessa peca?",
                
                "comprei e veio quebrado, quero devolver",
                "produto com defeito, não funciona",
                "vou acionar o procon, um absurdo",
                "quero meu dinheiro de volta urgente",
                
                "qual o prazo de entrega pro cep 12345-678",
                "comprei ontem, quando chega?",
                "meu frete ta atrasado, cade o produto",
                "envia ainda hoje se eu pagar agora?",
                
                "bom dia, tem em estoque?",
                "quantas unidades disponiveis?",
                "faz um desconto se levar 10?",
                "emite nota fiscal para cnpj?"
            ],
            "intent": [
                "compatibilidade", "compatibilidade", "compatibilidade", "compatibilidade",
                "reclamacao_critica", "reclamacao_critica", "reclamacao_critica", "reclamacao_critica",
                "logistica", "logistica", "logistica", "logistica",
                "duvida_geral", "duvida_geral", "duvida_geral", "duvida_geral"
            ]
        }
        
        df = pd.DataFrame(data)
        df['cleaned_text'] = df['text'].apply(self._clean_text)
        
        # Criando o pipeline Scikit-Learn
        pipeline = Pipeline([
            ('tfidf', TfidfVectorizer(ngram_range=(1, 2), max_features=1000)),
            ('svm', SVC(kernel='linear', probability=True, random_state=42))
        ])
        
        # Treinamento
        pipeline.fit(df['cleaned_text'], df['intent'])
        
        # Salvando o pipeline completo (Vetorizador + SVM)
        joblib.dump(pipeline, MODEL_PATH)
        self.model = pipeline
        self.is_loaded = True
        print(f"Modelo salvo com sucesso em {MODEL_PATH}")

    def _load_model(self):
        """Carrega o modelo do disco se existir"""
        if os.path.exists(MODEL_PATH):
            try:
                self.model = joblib.load(MODEL_PATH)
                self.is_loaded = True
                print("Modelo carregado com sucesso da memoria.")
            except Exception as e:
                print(f"Erro ao carregar modelo: {e}")
                self.is_loaded = False
        else:
            print("Modelo nao encontrado. Iniciando treinamento automatico...")
            self.train_initial_model()

    def predict(self, text: str) -> dict:
        """Realiza a inferencia em tempo real"""
        if not self.is_loaded or self.model is None:
            # Fallback de seguranca
            return {"intent": "desconhecido", "confidence": 0.0, "urgency_score": 0.0}
            
        cleaned = self._clean_text(text)
        
        # Predicao de classe
        prediction = self.model.predict([cleaned])[0]
        
        # Probabilidades
        probabilities = self.model.predict_proba([cleaned])[0]
        confidence = float(max(probabilities))
        
        # Regra de urgência baseada na intenção
        urgency_score = 0.1
        if prediction == "reclamacao_critica":
            # Urgencia base 0.8 + ajuste pela confianca da classe
            urgency_score = min(0.8 + (confidence * 0.2), 1.0)
        elif prediction == "logistica":
            urgency_score = 0.4
            
        return {
            "intent": prediction,
            "confidence": round(confidence, 4),
            "urgency_score": round(urgency_score, 4)
        }

# Instancia global para injecao de dependencia no FastAPI
nlp_service = NLPModelService()
