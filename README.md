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
