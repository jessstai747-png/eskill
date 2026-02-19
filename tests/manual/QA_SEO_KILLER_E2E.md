# Roteiro de QA End-to-End: SEO Killer (GSC, AI Images, AI Pricing)

Este documento guia a validação manual das funcionalidades do SEO Killer que foram integradas com dados reais.

## 1. GSC / SEO Killer – Aba GSC

### Pré-requisitos
- Conta no sistema com `account_id` configurado.
- Acesso a uma conta Google com propriedades no Search Console (para testar o fluxo de conexão).

### Fluxo de Teste
1. **Status Inicial (Desconectado)**
   - Navegue para a aba **Google Search Console** no painel SEO Killer.
   - Verifique se a API retorna desconectado em `/api/seo-killer/gsc/status`.
   - A UI deve exibir um botão "Conectar GSC" (ou similar).

2. **Conexão OAuth**
   - Clique em "Conectar GSC".
   - O sistema deve chamar `/api/seo-killer/gsc/auth-url` e redirecionar para o Google.
   - Complete a autorização.
   - O retorno deve ocorrer em `/api/seo-killer/gsc/callback`.
   - Verifique se o status agora indica "conectado".

3. **Visualização de Dados**
   - Na aba GSC, selecione um período (ex: últimos 28 dias).
   - O front-end deve chamar `/api/seo-killer/gsc/data`.
   - **Verificação de Dados:**
     - `clicks`, `impressions`, `ctr`, `position` devem ter valores numéricos.
     - Gráficos de linha devem renderizar com dados de `chartLabels`, `chartClicks`, `chartImpressions`.
     - Tabela de "Top Queries" deve listar termos reais com suas métricas.

## 2. AI Images – Painel de Imagens

### Pré-requisitos
- Um item real cadastrado na tabela `items` com `ml_item_id` válido e imagens no Mercado Livre.

### Fluxo de Teste
1. **Análise de Imagens**
   - Abra o painel de imagens de um item.
   - O sistema deve chamar `GET /api/ai/images/analyze/{itemId}`.
   - **Verificação:**
     - As imagens exibidas são as URLs reais do ML (`secure_url`).
     - Metadados (dimensões, tamanho) estão corretos.
     - Issues de qualidade (`type`, `severity`, `description`) aparecem se houver problemas.
     - Seção de "Melhores Práticas" e "Ordem Sugerida" deve estar preenchida.

2. **Reordenar Imagens**
   - Arraste/troque a ordem de fotos na UI.
   - O sistema deve enviar `POST /api/ai/images/reorder/{itemId}` com a nova lista de índices.
   - **Verificação:** Sem erros na resposta API. A ordem no anúncio oficial do ML deve mudar após alguns instantes.

3. **Remover Imagem**
   - Exclua uma imagem pelo painel.
   - O sistema deve enviar `DELETE /api/ai/images/remove` com `{ item_id, image_url }`.
   - **Verificação:** A imagem desaparece do painel e do anúncio no ML.

4. **Upload de Imagem**
   - Faça upload de um arquivo local via UI.
   - O sistema deve enviar `POST /api/ai/images/upload` (multipart).
   - **Verificação:** A nova imagem é processada, enviada ao ML e aparece na lista atualizada.

## 3. AI Pricing – Aba Preços

### Pré-requisitos
- Um item em `items` com `price` > 0.
- (Opcional) Dados em `price_history` para a categoria/marca do item para testes mais ricos de elasticidade.

### Fluxo de Teste
1. **Sugestão de Preço**
   - Na aba Pricing, solicite uma sugestão (ex: focar em Margem ou Volume).
   - API: `POST /api/ai/pricing/suggest` com `{ item_id, goal }`.
   - **Verificação:**
     - `current_price` deve ser o preço real do banco.
     - `suggested_price` deve ser um valor calculado diferente de zero (se houver dados).
     - Explicação da estratégia deve ser exibida.

2. **Elasticidade e Cenários**
   - API: `POST /api/ai/pricing/elasticity`.
   - **Verificação:**
     - Coeficiente de elasticidade numérico exibido (ex: -1.5).
     - Gráfico ou lista de cenários mostra:
       - Variação de preço (`price_change`).
       - Impacto no volume (`expected_volume_change`).
       - Impacto na receita líquida (`net_revenue_effect`).

3. **Análise Competitiva**
   - API: `GET /api/ai/pricing/competitive/{itemId}`.
   - **Verificação:**
     - Dados de concorrentes (`min`, `avg`, `max`) devem refletir o mercado.
     - Sua posição (`position`) e percentil devem estar corretos em relação aos dados.
     - Oportunidades ("Aumentar preço", "Baixar para ganhar Buy Box") devem aparecer como texto.

4. **Forecast de Receita**
   - API: `POST /api/ai/pricing/forecast` com pontos de preço.
   - **Verificação:**
     - Tabela de projeção exibe Volume e Receita estimados para cada preço.
     - Melhor cenário é destacado.
     - "Ganho Potencial" exibe a diferença em R$ para o preço atual.
     - Nível de confiança (0-100%) é exibido.
