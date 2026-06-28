<?php

/**
 * Prosedürel kısayollar (tercihen sınıfları doğrudan kullanın: ApiClient, ApiEnvelope, …).
 *
 * @param array<string, mixed>|null $json
 * @return array<string, mixed>
 */
function api_unwrap(?array $json): array
{
    return ApiEnvelope::data($json);
}

/**
 * @return list<string>
 */
function api_bases_member_api(): array
{
    return ApiBases::forMemberApi();
}

/**
 * @return list<string>
 */
function api_member_api_path_alternates(string $baseUrl, string $endpointFile): array
{
    return ApiMemberApi::pathAlternatesForBase($baseUrl, $endpointFile);
}
