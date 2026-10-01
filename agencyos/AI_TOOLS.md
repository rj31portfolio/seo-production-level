# Optional AI tools

`AIService` calls `AIProviderInterface`; `DeepSeekProvider` implements the optional provider. The request contract follows the [official DeepSeek chat-completions documentation](https://api-docs.deepseek.com/api/create-chat-completion/). Additional providers are not implemented.

Super Admin settings: provider, enablement, encrypted replaceable/clearable key, official HTTPS base URL, model, temperature, output-token cap, timeout, request-size limit and agency daily/monthly, user daily and project monthly limits. The model is configurable; the current default is `deepseek-flash`. Connection testing checks provider authentication through `/models`, not generation quality.

Credentials are encrypted with Laravel's application key and omitted from HTML, JavaScript and validation old-input flashes. Base URLs are restricted to the official DeepSeek host to avoid configurable-endpoint SSRF or leaking the credential to another host. No real key was configured or live billed generation performed in local verification.

AI requests reserve quota under the agency admission lock, run on the `seo` queue and store status plus provider token usage when available. Failed/reserved attempts count toward limits. Platform plan AI limits can further restrict the global caps. Estimated cost is unavailable because pricing is not configured. Limits do not apply to normal offline tools.

Tools: keyword ideas, title suggestions, description suggestions, content briefs, content optimization suggestions, FAQ suggestions, audit explanations, strategy, monthly plans, report summaries, assistant questions, internal-link suggestions, schema suggestions and content-calendar drafts. These outputs are saved plain-text recommendations for review, not automatically applied structured business objects or completed work.

Users explicitly submit known facts and optionally select a completed crawler run from the same project. Context includes only available URLs, measurements, checks, headings and extracted terms. Inputs exceeding the configured size are rejected rather than silently expanding the prompt. Collected measurements are stored separately and never replaced by AI output.

The provider must return bounded JSON containing plain-text recommendations. Truncated/invalid responses and provider failures produce safe errors; external raw error bodies and credentials are never displayed. Recommendations are escaped and labeled **AI-generated recommendation based on available project data**. Prompts forbid invented SEO/search/authority metrics, but human review remains required because generative output can still be wrong.

Outstanding: editable structured briefs/calendars, conversational multi-turn memory, per-request cost estimates, AI-to-task approval workflows, advanced intent/cluster refinement and live provider validation with authorized credentials.
