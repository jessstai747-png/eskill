# Page Design — /dashboard/seo-killer (desktop-first)

## Layout
- Base em **Bootstrap grid (12 col)** com espaçamento consistente (gap 16–24px).
- Estrutura: **2 colunas** no desktop (conteúdo principal 8/9 col + sidebar 3/4 col). No mobile, empilhar e transformar “Ações rápidas” em barra fixa inferior.
- Renderização progressiva: carregar dados essenciais do “Dashboard” primeiro; conteúdo pesado (gráficos, listas grandes, modais) sob demanda.

## Meta Information
- Title: "SEO Killer — Otimize anúncios do Mercado Livre"
- Description: "Diagnóstico e otimização de títulos, descrições e ficha técnica."
- Open Graph: `og:title`, `og:description`, `og:type=website` (mesmo que área logada, útil para previews internos).

## Global Styles
- Tokens recomendados:
  - `--seo-primary` (já existe) como cor de ação; garantir contraste AA.
  - Tipografia: base 14–16px; títulos 24–32px; usar `fw-bold` com parcimônia.
  - Estados: foco visível (outline) em botões/cards; hover sem depender apenas de cor.
  - Motion: respeitar `prefers-reduced-motion` (desabilitar shimmer/pulse quando necessário).

## Page Structure
1) Header da página (já existe) + microcopy “o que fazer agora”.
2) Seção “Comece por aqui” (above the fold) com **1 CTA primário**.
3) Cards de status (4 métricas) + “Atualizado há X min”.
4) Ferramentas (grid) com rótulos, benefícios e “abre modal”.
5) Sidebar: Ações rápidas + AutoPilot (com estados claros).
6) Atividade recente + Top performers (tabelas responsivas).

## Sections & Components
### 1) Above-the-fold: valor + CTA
- Componente: `HeroActionCard`
  - Título: "Aumente a exposição dos seus anúncios"
  - Subtítulo: "Rode um diagnóstico e aplique melhorias com 1 clique."
  - CTA primário: **"Rodar diagnóstico agora"**
  - CTA secundário: "Otimizar em lote"
  - Estado: se não houver conta ativa, trocar CTA por **"Selecionar conta"**.

### 2) Cards de ferramentas (acessibilidade e clareza)
- Trocar `<div onclick>` por:
  - `<button type="button" class="tool-card" aria-label="Abrir Gerador de Títulos (abre modal)">…</button>`
- Conteúdo do card:
  - Nome (curto) + 1 linha de benefício + “Tempo estimado” (ex.: “~30s”).
- Estado PRO:
  - Badge com texto (não só cor) e tooltip acessível.

### 3) Abas
- Manter `role="tablist"/tab/tabpanel` e:
  - Sincronizar com querystring (ex.: `?tab=performance`) para deep-link.
  - Lazy-load: somente inicializar JS/gráficos ao ativar a aba.

### 4) AutoPilot (conversão)
- Card com:
  - Explicação: “O AutoPilot roda otimizações diárias dentro dos limites que você definir.”
  - Controles: Toggle + botão "Configurar limites".
  - Prova: mostrar “Última execução” e “Impacto (score médio +X)” quando disponível.

### 5) Loading, erros e feedback
- Loading:
  - Skeleton para listas (atividade/top performers) e spinner apenas para ações.
- Erros:
  - Toast com **ação** (Retry) e link “Ver detalhes”.
  - Evitar `alert()`.
- A11y:
  - Região `aria-live="polite"` para toasts/status.

## Critérios de validação (UX, conversão, performance, acessibilidade)
- Conversão/uso:
  - +15–25% de cliques no CTA primário ("Rodar diagnóstico agora").
  - +10% de ativação do AutoPilot (usuários elegíveis), sem aumentar tickets de suporte.
- UX:
  - Tempo até primeira ação (clique em diagnóstico/ferramenta) reduzido.
  - Estados vazios sem “beco sem saída” (sempre 1 CTA possível).
- Performance (desktop):
  - Remover requisições duplicadas ao carregar (diagnose/autopilot/history).
  - Carregar Chart.js apenas quando necessário.
  - Lighthouse Performance melhorado vs. baseline da rota.
- Acessibilidade:
  - Navegação completa por teclado (Tab/Shift+Tab/Enter/Espaço) em cards e abas.
  - Foco visível e ordem de foco lógica.
  - Respeitar `prefers-reduced-motion`.
