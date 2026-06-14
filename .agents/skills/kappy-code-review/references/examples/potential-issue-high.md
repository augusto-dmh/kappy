<!-- ANCHOR: app-modules/github-app/src/Http/Middleware/VerifyGithubWebhookSignature.php:34 -->

⚠️ **Potential issue** | 🔴 **High**

**Comparação de assinatura não é constant-time e aceita header ausente**

A verificação compara a assinatura com `hash_hmac(...) === $signature` (linha 34). Duas falhas de segurança: (1) `===` em strings é vulnerável a timing attack — use `hash_equals()`; (2) quando o header `X-Hub-Signature-256` está ausente, `$signature` é `null` e a expressão curto-circuita de um jeito que, dependendo do retorno do `request->header()`, deixa passar requisições sem assinatura. `laravel-best-practices/rules/security.md` exige comparação constant-time para segredos e rejeição explícita quando o material de verificação falta. O App do GitHub sempre envia esse header em produção, então sua ausência é sinal de requisição forjada.

Fix sugerido:

```php
$signature = $request->header('X-Hub-Signature-256');

if ($signature === null || ! hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
    abort(401);
}
```

**🤖 Prompt for AI Agents**

```
Verify each finding against the current code and only fix it if needed.

In app-modules/github-app/src/Http/Middleware/VerifyGithubWebhookSignature.php around line 34, the signature check uses string === comparison and does not explicitly reject a missing X-Hub-Signature-256 header. Replace the comparison with hash_equals() (constant-time), build the expected value as 'sha256='.hash_hmac('sha256', $request->getContent(), $secret), and abort(401) when the header is null or the comparison fails. Add a Feature test asserting a request with a missing/invalid signature returns 401 and one with a valid signature passes.
```
