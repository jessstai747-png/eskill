# Sons de Notificação

Este diretório contém os arquivos de áudio para notificações em tempo real.

## Arquivos

| Arquivo | Descrição |
|---------|-----------|| order.mp3 | Som de novo pedido - 3 notas ascendentes |
| question.mp3 | Som de nova pergunta - 2 notas |
| message.mp3 | Som de nova mensagem - 1 nota suave |
| cash_register.mp3 | Som de caixa registradora |
| cha_ching.mp3 | Som cha-ching (dinheiro) |
| bell.mp3 | Som de sino |
| chime.mp3 | Som de campainha |
| pop.mp3 | Som pop curto |
| alert.mp3 | Som de alerta |
| success.mp3 | Som de sucesso |
| notification.mp3 | Som genérico de notificação |

## Personalizando Sons

Você pode substituir qualquer arquivo MP3 por um som personalizado.
Recomendações:
- Duração: 0.5 a 2 segundos
- Formato: MP3
- Taxa de bits: 128kbps ou superior
- Volume: Normalizado

## Configurações

As configurações de som podem ser alteradas em:
- Dashboard > Configurações > Notificações
- Ou via API: POST /api/notifications/realtime/settings
