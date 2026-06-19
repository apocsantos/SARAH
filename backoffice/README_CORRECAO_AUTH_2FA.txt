Correção aplicada

Esta versão mantém a autenticação existente do backoffice:
- usa lib/bootstrap.php
- respeita ensure_auth()
- respeita require_role()
- mantém login + 2FA + sessão existentes

Correções principais:
1. Criado lib/common.php
   - faltava no teu servidor e causava erro fatal no library_browser.php.
   - agora é uma camada de compatibilidade sobre bootstrap.php.

2. Protegido o navegador de biblioteca
   - library_browser.php passa por lib/common.php -> bootstrap.php -> require_role().

3. Protegido api/library_tree.php
   - também passa por lib/common.php e só responde a utilizadores autenticados.

4. Criado api/save_seed.php
   - compatível com editores que usem esse endpoint.

5. Mantido api/publish_package.php
   - continua a ser o endpoint usado pelo editor integrado atual.

6. Melhorado redirecionamento de ensure_auth()
   - em APIs responde JSON 401 em vez de tentar redirecionar para /api/index.php.

7. Dashboard atualizado
   - inclui link direto para Navegar biblioteca.

Instalação:
- Substitui a pasta backoffice atual pelo conteúdo deste ZIP, mantendo config.php se preferires.
- Se fizeres upload parcial, garante pelo menos estes ficheiros:
  lib/common.php
  lib/helpers.php
  library_browser.php
  api/library_tree.php
  api/save_seed.php
  dashboard.php

Atenção:
- Os SVG devem ficar em storage/icons/
- Exemplo: storage/icons/importados/agua.svg
- O caminho no seed.json será: icons/importados/agua.svg
