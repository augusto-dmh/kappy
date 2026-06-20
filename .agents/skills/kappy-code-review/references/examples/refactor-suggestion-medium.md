<!-- ANCHOR: app-modules/github-app/src/Http/Controllers/GithubWebhookController.php:28 -->

🛠️ **Refactor suggestion** | 🟠 **Medium**

**Controller embute a dedupe e o enfileiramento que deveriam estar num service**

O método `store` (linha 28) monta a query de dedupe por `delivery_id` (`WebhookDelivery::where(...)->exists()`, linha 33), cria o registro e despacha o job — toda a orquestração inline. `laravel-best-practices/rules/architecture.md`: controllers ficam finos (input → autorização → delegação → resposta), e a lógica de orquestração concentra-se em services. Isso também diverge do resto da branch, onde `VerifyGithubWebhookSignature` faz uma coisa só e a persistência vive em `WebhookDelivery`.

Fix sugerido: extrair um `WebhookDeliveryIntakeService` que receba o payload já verificado, aplique o dedupe e despache `ProcessWebhookDelivery`, deixando o controller em input → service → resposta `204`. Facilita o teste do dispatch pedido no comentário de B1.

**🤖 Prompt for AI Agents**

```
Verify each finding against the current code and only fix it if needed.

In app-modules/github-app/src/Http/Controllers/GithubWebhookController.php::store, the controller inlines the delivery_id dedupe lookup, the WebhookDelivery creation, and the ProcessWebhookDelivery dispatch. The repo's conventions (laravel-best-practices/rules/architecture.md) require thin controllers with orchestration in a service. Extract a WebhookDeliveryIntakeService that takes the verified payload, performs the dedupe, persists the delivery, and dispatches the job; reduce the controller to reading input, calling the service, and returning 204. Keep behavior identical and covered by the existing Feature test.
```
