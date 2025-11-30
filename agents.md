## Contexto
- GLPI 10
- Plugin UniApp gera endpoints que serao consumidos pelo aplicativo mobile.
- As configuracoes de FCM e visual devem ser mantidas em uma tabela dedicada ao plugin.
- Este agente sempre insere comentarios em portugues (sem acento) para facilitar a manutencao.

## Prioridades
1. Evitar alterar tabelas nativas como `glpi_users`; usar tabelas proprias com relacao a `users_id`.
2. Garantir que os tokens FCM sejam persistidos em `glpi_plugin_uniapp_user_tokens`.
3. Oferecer uma interface alinhada ao modelo em `modelodedesign.html` para configurar os dados do Firebase e as cores.
