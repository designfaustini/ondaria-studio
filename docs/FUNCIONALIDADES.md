# Funções: programa original × Ondaria Studio

Levantamento feito pelo manual do usuário (arquivo de ajuda que acompanha o programa) e pelo comportamento visível. Nenhum código, imagem ou texto do programa original foi copiado.

## Implementado

| Função do manual | Como ficou na web |
|---|---|
| Lista de execução: tocar, parar, pausar, próxima, ±30 s, parar após a atual | Igual, no rodapé do estúdio |
| Cores: vermelho (no ar), verde (próxima), azul (seleção) | Igual |
| Modos Normal, Repetir, Embaralhar, Aleatório, Manual | Igual |
| Apagar ao tocar, Loop | Igual |
| Duplo clique marca a próxima (ou toca, pela opção) | Igual (Opções › Geral) |
| Arquivos WAV/MP3/OGG/WMA | MP3, OGG/Opus, WAV, FLAC, M4A/AAC, WEBM. **WMA não**: navegadores não decodificam |
| Listas `.lst/.m3u/.pls` dentro da lista (carregar outra) | Item "Carregar lista" |
| Rotações `.rot` e sequências `.seq` | Listas do tipo rotação (ponteiro guardado no servidor) e sequência |
| Comandos parar/tocar, pausa (0–3600 s) | Igual |
| Faixa aleatória de pasta, sem repetir até tocar todas | Igual, inclui subpastas; histórico no servidor |
| Post-it (`.txt/.html`) | Lembrete em texto que aparece na tela |
| Carregar arquivo de eventos (`.evt`) | "Carregar grade de eventos" (grades nomeadas) |
| Rádio online (`.stream`) por tempo | Igual. O stream precisa liberar CORS para entrar nos medidores e na transmissão; sem CORS toca direto na placa de saída |
| Satélite: abrir a entrada de linha por tempo, conectar/desconectar | Entrada de áudio escolhida em Dispositivos, por tempo ou por comando, e botão "Satélite" |
| Hora certa (`HRSxx`, `HRSxx_O`, `MINxx`) | Igual, pasta do tipo "Hora certa" |
| Temperatura (`TMPxxx`, `TMPNxxx`) e umidade (`HUMxxx`) | Igual. O clima vem manual ou automático pelo **Open-Meteo** (substitui o Weather Watcher), com "não anunciar dados velhos" |
| Sobreposição/fundido, detector de fim de música, fade na sobreposição e no stop | Igual (Opções › Fundido) |
| Sobreposição por arquivo `Nome~2.mp3` | Igual (lido no envio e editável) |
| Detector de silêncio | Igual |
| Talk over (abaixa a música e abre o microfone) | Igual |
| 10 páginas × 9 vinhetas, teclas 1–9, modos Normal/Loop/Sweeper | Igual; arrastar arquivo para o botão também funciona |
| Sweepers/voicetracking (locução sobre a música no segundo X) | Igual (página Sweepers) |
| Eventos: início, expiração, diário/de hora em hora/horas escolhidas, imediato ou espera, prioridade alta/baixa, espera máxima | Igual, mais dias da semana. Testado: imediato interrompe, com espera aguarda a faixa, prioridade baixa é descartada |
| Painel "próximos eventos" (15 min), tocar pendentes, descartar, ativar/desativar | Igual |
| 4 players auxiliares, cada um numa placa de som | Igual (quantidade por plano). Escolha de placa exige Chrome/Edge |
| Pré-escuta (cue) em placa separada | Igual |
| Registro de execução e navegador de registros, exportação | Busca por arquivo, erros ou tudo, por período; exporta TXT e CSV |
| `CurrentSong.txt` para RDS/site | `https://dominio/np/<radio>.txt` e `.json` (públicos) |
| Senha nas opções | Usuários com papéis: dono, administrador, operador |
| Abrir a última lista, iniciar tocando, impedir duas instâncias | Igual; só o "estúdio principal" dispara eventos |
| Busca na lista e em pastas com curinga `*`, por nome e tags ID3 | Igual |
| Duração da seleção, atualizar duração | Igual; a duração real também é corrigida ao tocar |

## Extras do SaaS

Multi-rádio com planos (espaço, usuários, players auxiliares, streaming), painel do dono da plataforma, cadastro público, upload em partes de 1 MB (passa nos limites do cPanel), isolamento total entre rádios e envio da saída para AzuraCast/Liquidsoap (protocolo *webcast* via WebSocket).

## O que não existe (e por quê)

| Função | Motivo |
|---|---|
| Rodar sem nenhum computador ligado | Num cPanel compartilhado não dá para manter um processo de áudio 24 h nem um servidor Icecast. O áudio é tocado e mixado no navegador do computador da rádio; o servidor guarda biblioteca, listas, grades e registros. Para uma rádio 100% na nuvem, é preciso VPS com Liquidsoap/AzuraCast |
| Envio direto para Icecast/Shoutcast "puros" | O navegador não fala o protocolo de source do Icecast. Opções: AzuraCast/Liquidsoap (suportado) ou um encoder no PC (BUTT) capturando a saída |
| Detector de tons DTMF | Não implementado nesta versão (o próprio manual diz que era recurso de outra edição) |
| Executar `.exe`/`.bat` e o menu "Programas" | O navegador não executa programas locais, por segurança |
| Abrir o mixer do Windows | Não existe no navegador; use Dispositivos |
| Arquivos WMA | Não tocam em navegadores; converta para MP3/OGG |
| Cobrança automática | Os planos existem, mas a troca é manual no painel Plataforma (dá para integrar Mercado Pago depois) |

## Pontos a validar no uso real

- **Transmissão webcast**: implementada conforme o protocolo usado pelo Web DJ do AzuraCast e pelo `input.harbor` do Liquidsoap. **Não foi testada contra um servidor real** neste desenvolvimento.
- **Aba em segundo plano**: o agendador usa um *Web Worker* para não ser freado. Mesmo assim, deixe o computador sem suspensão e o navegador aberto.
- Escolha de placa de som (`setSinkId`) só funciona em Chrome/Edge. No Firefox, a saída é a placa padrão.
