# Ondaria Studio

Automação de rádio como serviço (SaaS), 100% no navegador, para hospedagem cPanel com **PHP 8.5 + MySQL**.

Reproduz as funções de um programa clássico de automação de rádio para Windows num sistema web multi-rádio. O projeto foi escrito do zero (*clean-room*), a partir do manual do usuário e do uso normal desse programa, **sem** descompilar o executável, que tem licença proibindo engenharia reversa, e sem usar nome, marca, imagens ou código dele.

- Deploy no cPanel: [docs/DEPLOY-CPANEL.md](docs/DEPLOY-CPANEL.md)
- Mapa de funções e limitações: [docs/FUNCIONALIDADES.md](docs/FUNCIONALIDADES.md)

## Rodar no PC (desenvolvimento)

Rode no PowerShell, na pasta do projeto:

```bash
copy .env.example .env
```

No `.env`, use `APP_ENV=local`, `APP_DEBUG=true`, `DB_DRIVER=sqlite` e `APP_URL=http://127.0.0.1:8090`. Depois:

```bash
php bin/console.php migrate
php bin/console.php demo
php -S 127.0.0.1:8090 -t public public/index.php
```

O comando `demo` cria a "Rádio Demonstração", com músicas de teste (tons gerados), arquivos de hora certa, temperatura e umidade, vinhetas e um evento de hora em hora. Ele imprime o login e a senha (ou use `DEMO_PASSWORD` no ambiente).

## Áudios de demonstração (pasta `audios/`)

Os arquivos da pasta `audios/` formam uma biblioteca de exemplo **compartilhada por todas as rádios**, para quem acabou de se cadastrar testar o sistema.

- **Pastas:** cada subpasta vira uma pasta da seção "Demonstração" na Biblioteca. Pastas chamadas `HCerta`/`Hora`, `Temperatura` e `Umidade` viram locuções automáticas.
- **Hora certa:** aceita um arquivo por minuto, `HHMM` (ex.: `0235.mp3`, que também serve para 14:35), ou a hora e o minuto separados, `HRSxx` + `MINxx`.
- **Somente leitura:** todas as rádios ouvem e usam esses áudios em listas, eventos, vinhetas e sweepers, mas ninguém edita, apaga ou envia arquivos para eles. A pasta não fica acessível pela web: os áudios só saem pelo sistema, para quem está logado.
- **Arquivos de cada rádio:** os que a rádio envia continuam visíveis só para ela.
- **Leitura automática:** novos arquivos colocados em `audios/` aparecem sozinhos ao abrir a Biblioteca. Para forçar a releitura, rode o comando abaixo.

```bash
php bin/console.php demo:sync
```

Arquivos com o mesmo nome em MP3 e WAV contam uma vez só (fica o MP3). Formatos aceitos: MP3, OGG, WAV, FLAC, M4A/AAC e WEBM; os demais (`.jpg`, `.pk`, `.sfk`) são ignorados.

## Estrutura

```
app/Core         roteador, banco (MySQL/SQLite), sessão, CSRF, autenticação
app/Services     mídia (upload em partes, Range), ID3, clima, itens, rádios
app/Controllers  páginas e API JSON do estúdio
views/           telas PHP
public/assets/js audio.js (motor Web Audio), studio.js (estúdio), manage.js (gestão)
database/        schema.sql
bin/console.php  migrate | superadmin | demo | cleanup
```
