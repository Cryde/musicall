<?php declare(strict_types=1);

namespace App\Serializer\Encoder;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

final class MultipartDecoder implements DecoderInterface
{
    public const FORMAT = 'multipart';

    /**
     * Fields an operation wants as sent, never JSON decoded: free text such as a chat caption, where
     * `["intro","verse"]` is something a member typed, not an array (#973).
     */
    public const RAW_FIELDS = 'multipart_raw_fields';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decode(string $data, string $format, array $context = []): ?array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof \Symfony\Component\HttpFoundation\Request) {
            return null;
        }

        /** @var list<string> $rawFields */
        $rawFields = $context[self::RAW_FIELDS] ?? [];

        $fields = [];
        foreach ($request->request->all() as $name => $element) {
            $fields[$name] = match (true) {
                is_array($element) => $element,
                !is_string($element) => (string) $element,
                in_array($name, $rawFields, true) => $element,
                default => self::decodeJson($element),
            };
        }

        return $fields + $request->files->all();
    }

    /**
     * Multipart form values will be encoded in JSON.
     *
     * @return string|array<mixed>
     */
    private static function decodeJson(string $element): string|array
    {
        $decoded = json_decode($element, true);

        return \is_array($decoded) ? $decoded : $element;
    }

    public function supportsDecoding(string $format): bool
    {
        return self::FORMAT === $format;
    }
}
