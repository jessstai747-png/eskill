# Ícones PWA - Mercado Livre Manager

Esta pasta contém os ícones necessários para o PWA (Progressive Web App).

## Ícones Necessários

Os seguintes arquivos de ícone são necessários:

### Ícones Principais
- `icon-72x72.png` - 72x72 pixels
- `icon-96x96.png` - 96x96 pixels
- `icon-128x128.png` - 128x128 pixels
- `icon-144x144.png` - 144x144 pixels
- `icon-152x152.png` - 152x152 pixels
- `icon-192x192.png` - 192x192 pixels (obrigatório)
- `icon-384x384.png` - 384x384 pixels
- `icon-512x512.png` - 512x512 pixels (obrigatório)

### Badge para Notificações
- `badge-72x72.png` - Ícone pequeno para notificações

### Ícones de Atalhos
- `orders-96x96.png` - Ícone para atalho de Pedidos
- `analysis-96x96.png` - Ícone para atalho de Análise
- `seo-96x96.png` - Ícone para atalho de SEO

### Splash Screens (iOS)
- `splash-640x1136.png` - iPhone 5
- `splash-750x1334.png` - iPhone 6/7/8
- `splash-1242x2208.png` - iPhone 6/7/8 Plus

## Geração de Ícones

Você pode gerar todos os ícones a partir de uma imagem base usando ferramentas como:

1. **Real Favicon Generator** (https://realfavicongenerator.net/)
2. **PWA Asset Generator** (https://www.pwabuilder.com/imageGenerator)
3. **Favicon.io** (https://favicon.io/)

### Especificações
- Formato: PNG
- Fundo: Transparente ou cor sólida (#0d6efd)
- Design: Ícone de loja/carrinho com as letras "ML"
- Propósito: `maskable any` (funciona em Android e iOS)

## Exemplo de Geração com ImageMagick

```bash
# A partir de um ícone SVG ou PNG grande
convert source-icon.png -resize 512x512 icon-512x512.png
convert source-icon.png -resize 384x384 icon-384x384.png
convert source-icon.png -resize 192x192 icon-192x192.png
convert source-icon.png -resize 152x152 icon-152x152.png
convert source-icon.png -resize 144x144 icon-144x144.png
convert source-icon.png -resize 128x128 icon-128x128.png
convert source-icon.png -resize 96x96 icon-96x96.png
convert source-icon.png -resize 72x72 icon-72x72.png
```

## Verificação

Após adicionar os ícones, verifique:
1. Acesse `/manifest.json` no navegador
2. Use o Chrome DevTools > Application > Manifest
3. Teste a instalação do PWA
