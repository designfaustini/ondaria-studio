# LGPD, segurança e governança — guia de operação

Documento interno. Não é parecer jurídico: os textos de `/termos`, `/privacidade` e `/direitos-autorais` são modelos e devem ser revisados por um advogado antes de abrir o cadastro ao público.

## 1. Antes de abrir ao público

- [ ] Preencher no `.env` os campos `COMPANY_*`, `CONTACT_EMAIL`, `DPO_*`, `COPYRIGHT_EMAIL`, `LEGAL_FORO` e `HOSTING_INFO`. Enquanto algum faltar, as páginas legais mostram "Rascunho".
- [ ] Revisar os termos com um advogado e só então ligar `SIGNUP_ENABLED=true`.
- [ ] Preencher o titular no arquivo `LICENSE`. Considerar o registro do software e da marca no INPI.
- [ ] Agendar o cron diário do `cleanup` (ver DEPLOY-CPANEL.md).
- [ ] Configurar o backup (seção 4).
- [ ] Confirmar o HTTPS e que `/storage/.htaccess`, `/bin/console.php` e `/.env` respondem 403 ou 404.

## 2. Inventário de dados e retenção

| Dado | Onde fica | Prazo | Quem apaga |
|---|---|---|---|
| Conta (nome, e-mail, hash da senha, aceite dos termos) | `users` | Até a exclusão | Pessoa em /conta, ou o encerramento da rádio |
| Registros de acesso (IP, evento, navegador) | `access_log` | `ACCESS_LOG_DAYS` (180): Marco Civil, art. 15 | `cleanup` |
| Tentativas de login | `login_attempts` | 24 h | `cleanup` e o próprio login |
| Auditoria | `audit_log` | `AUDIT_RETENTION_DAYS` (365) | `cleanup` |
| Registro de execução | `play_log` | `LOG_RETENTION_DAYS` (730) | `cleanup` |
| Áudios e dados da rádio | `storage/media/<id>` e tabelas | Até o encerramento + `CLOSING_GRACE_DAYS` | `cleanup` |
| Envios incompletos | `storage/tmp/<id>/*.part` | 2 h | Upload e `cleanup` |
| Sessões | `storage/sessions` | 12 h sem uso | PHP |
| Log de erros | `storage/logs` | Rotação a cada 5 MB; arquivos antigos guardados por 90 dias | `cleanup` |

## 3. Direitos do titular (LGPD, art. 18)

- **Corrigir, exportar (JSON) e excluir:** a própria pessoa faz em `/conta`.
- **Encerrar a rádio:** o dono agenda em `/conta`, com carência e possibilidade de cancelar.
- **Outros pedidos** (por exemplo, saber com quem os dados foram compartilhados): pelo e-mail do encarregado, com resposta em até 15 dias. Registrar o atendimento.
- **Dados que a rádio controla** (usuários dela, conteúdo): encaminhar o pedido ao dono da rádio, já que nesses dados a plataforma é operadora.

## 4. Backup (art. 46)

- **Diário:** banco (`mysqldump` ou backup do cPanel) e a pasta `storage/media`.
- **Cifrado e fora do servidor:** por exemplo, um disco externo ou um armazenamento de outro provedor.
- **Retenção:** 30 dias. Testar a restauração uma vez por trimestre.
- **Não versionar backups** no Git.

## 5. Plano de resposta a incidentes (art. 48 e Resolução CD/ANPD 15/2024)

1. **Conter:** trocar as senhas do banco e do superadmin, e suspender as rádios afetadas em /admin.
2. **Avaliar:** que dados, quantas pessoas e qual risco. Usar o `access_log`, o `audit_log` e os logs da hospedagem.
3. **Comunicar:** se houver risco ou dano relevante, avisar a ANPD (formulário no gov.br) e os titulares em até 3 dias úteis.
4. **Registrar:** guardar um relato do incidente (data, causa, medidas) por 5 anos.
5. **Corrigir:** aplicar a correção, revisar e, se for o caso, rodar uma nova auditoria (skill `security-audit`).

## 6. Direitos autorais e notificações

- **Canal:** `COPYRIGHT_EMAIL`. Analisar em até 5 dias úteis.
- **Notificação válida:**
  - suspender a rádio em /admin, ou pedir ao dono que remova o conteúdo;
  - registrar a notificação;
  - avisar a rádio.
- **Reincidência:** encerrar a rádio, conforme os termos.
- **Por que agir após a notificação:** o art. 19 do Marco Civil não se aplica a direito autoral (§2º). Por isso a remoção depois da notificação reduz o risco.

## 7. Controles de segurança em vigor

- **Senhas:** hash bcrypt, mínimo de 10 caracteres. A troca de senha encerra as outras sessões.
- **Login:**
  - limite de tentativas por IP e por "e-mail + IP";
  - o mesmo tempo de resposta para e-mail existente ou não;
  - registro de acesso.
- **Formulários:** CSRF com token e verificação de origem. Cookie `__Host-`, HttpOnly e SameSite.
- **Cabeçalhos:** CSP, `nosniff`, X-Frame-Options DENY e HSTS em HTTPS.
- **Isolamento entre rádios:** toda consulta filtra por `station_id`. A senha do dono só pode ser trocada por ele mesmo.
- **Upload:** formato detectado pelo conteúdo, nome aleatório, fora da pasta pública, cota com os envios em andamento, até 3 envios simultâneos.
- **Console:** `bin/console.php` só roda no terminal, e `demo` é bloqueado em produção.
- **Credencial de streaming:** só chega ao navegador quando a transmissão está ligada.
