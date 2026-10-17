<?php declare(strict_types=1);

namespace App\Tests\Unit\State\Provider;

use ApiPlatform\Metadata\Error as ErrorOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ApiResource\Error;
use ApiPlatform\State\ProviderInterface;
use App\Exception\TranslatableHttpException;
use App\State\Provider\TranslatedErrorProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class TranslatedErrorProviderTest extends TestCase
{
    public function test_a_key_is_rendered_in_the_detail_and_the_description(): void
    {
        $error = $this->provide(new NotFoundHttpException('test.not_found'), 'test.not_found');

        self::assertSame('Élément introuvable', $error->getDetail());
        self::assertSame('Élément introuvable', $error->getDescription());
    }

    public function test_a_translatable_exception_brings_its_parameters(): void
    {
        $exception = new TranslatableHttpException(Response::HTTP_BAD_REQUEST, 'test.not_found_in_space', ['id' => '1234']);

        $error = $this->provide($exception, 'test.not_found_in_space');

        self::assertSame("Tâche 1234 introuvable dans l'espace", $error->getDetail());
        self::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function test_a_message_that_is_not_a_key_is_left_unchanged(): void
    {
        $error = $this->provide(new NotFoundHttpException('Fichier introuvable'), 'Fichier introuvable');

        self::assertSame('Fichier introuvable', $error->getDetail());
    }

    /**
     * An unknown message is not read as ICU, so an apostrophe or a brace in it survives.
     */
    public function test_a_message_with_icu_syntax_that_is_not_a_key_is_left_unchanged(): void
    {
        $message = "Le champ {name} n'est pas valide, d'{autre} non plus";

        $error = $this->provide(new NotFoundHttpException($message), $message);

        self::assertSame($message, $error->getDetail());
    }

    /**
     * On a 5xx the inner provider swaps the message for a generic one, which must not be rendered from
     * the exception's key.
     */
    public function test_a_detail_the_inner_provider_replaced_is_kept(): void
    {
        $exception = new TranslatableHttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'test.not_found_in_space', ['id' => '1']);

        $error = $this->provide($exception, 'Internal Server Error');

        self::assertSame('Internal Server Error', $error->getDetail());
    }

    private function provide(\Throwable $exception, string $detail): Error
    {
        $inner = new class (new Error('An error occurred', $detail, 400)) implements ProviderInterface {
            public function __construct(private readonly Error $error)
            {
            }

            public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
            {
                return $this->error;
            }
        };

        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'test.not_found' => 'Élément introuvable',
            'test.not_found_in_space' => "Tâche {id} introuvable dans l'espace",
        ], 'fr', 'messages+intl-icu');

        $request = new Request();
        $request->attributes->set('exception', $exception);

        $error = (new TranslatedErrorProvider($inner, $translator))->provide(new ErrorOperation(), [], ['request' => $request]);
        self::assertInstanceOf(Error::class, $error);

        return $error;
    }
}
