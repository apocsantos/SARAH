SARAH Integrated Production

Pacote final integrado:
- backend PHP + MySQL
- multi-user
- login com password + 2FA TOTP
- roles: superadmin / editor / viewer
- editor ACC integrado no backend (editor.php)
- biblioteca central SVG no servidor
- seed.json compatível com SARAH
- publicação direta do editor para o servidor
- upload de ZIP
- hardening v2:
  - session secure / idle timeout
  - lockout por IP e utilizador
  - reset de password com token
  - política mínima de password
  - proteção storage
  - sanitizer SVG básico
  - install lock

INSTALAÇÃO
1) Copia config.sample.php para config.php
2) Preenche os dados MySQL
3) Importa install.sql
4) Abre install.php no browser
5) Cria o primeiro superadmin
6) Confirma HTTPS e usa:
   'session_secure' => true
7) Depois apaga ou protege install.php

ESTRUTURA
- index.php                -> login
- dashboard.php            -> painel principal
- editor.php               -> editor ACC integrado
- upload.php               -> importação ZIP
- users.php                -> gestão de utilizadores
- forgot_password.php      -> pedido de reset
- reset_password.php       -> redefinição
- setup.php                -> mostra segredo TOTP do utilizador
- api/library_manifest.php -> biblioteca SVG
- api/seed_api.php         -> seed.json
- api/publish_package.php  -> publicação do editor
- storage/seed.json
- storage/icons/

NOTAS
- O editor já está embutido no backend: não precisas de HTML separado.
- O editor carrega a biblioteca SVG do servidor, carrega o seed atual e publica de volta.
- O reset por email usa mail(); para produção séria recomenda-se SMTP autenticado.
