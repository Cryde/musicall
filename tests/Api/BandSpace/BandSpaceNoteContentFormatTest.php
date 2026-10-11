<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\BandSpace\NoteContent;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A note's body is held to the rich text format the web editor produces (#1012).
 */
#[ResetDatabase]
class BandSpaceNoteContentFormatTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array PATCH_HEADERS = ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    private const array STORED_CONTENT = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Avant']]]]];

    public function test_a_table_header_is_refused_and_the_note_is_unchanged(): void
    {
        [$user, $bandSpace, $note] = $this->seedNote();
        $content = ['type' => 'doc', 'content' => [['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [['type' => 'tableHeader']]]]]]];

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id, ['content' => $content, 'expected_content_version' => 1], self::PATCH_HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . NoteContent::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Le contenu contient un élément non pris en charge (« tableHeader »)',
                    'code' => NoteContent::ERROR_CODE,
                ],
            ],
            'detail' => 'content: Le contenu contient un élément non pris en charge (« tableHeader »)',
            'type' => '/validation_errors/' . NoteContent::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'content: Le contenu contient un élément non pris en charge (« tableHeader »)',
        ]);
        $this->assertStoredContent($note->id, self::STORED_CONTENT, 1);
    }

    public function test_a_body_too_large_is_refused(): void
    {
        [$user, $bandSpace, $note] = $this->seedNote();
        $content = ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => str_repeat('a', NoteContent::MAX_CONTENT_BYTES)]]],
        ]];

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id, ['content' => $content, 'expected_content_version' => 1], self::PATCH_HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . NoteContent::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Le contenu de la note est trop volumineux (limite : 500 Ko)',
                    'code' => NoteContent::ERROR_CODE,
                ],
            ],
            'detail' => 'content: Le contenu de la note est trop volumineux (limite : 500 Ko)',
            'type' => '/validation_errors/' . NoteContent::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'content: Le contenu de la note est trop volumineux (limite : 500 Ko)',
        ]);
        $this->assertStoredContent($note->id, self::STORED_CONTENT, 1);
    }

    /** @return array{0: \App\Entity\User, 1: \App\Entity\BandSpace\BandSpace, 2: \App\Entity\BandSpace\BandSpaceNote} */
    private function seedNote(): array
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $note = BandSpaceNoteFactory::new([
            'createdBy' => $user,
            'bandSpace' => $bandSpace,
            'title' => 'Setlist',
            'position' => 0,
            'content' => self::STORED_CONTENT,
            'creationDatetime' => new \DateTime('2026-10-01 10:00:00'),
        ])->create();

        return [$user, $bandSpace, $note];
    }

    private function assertStoredContent(string $noteId, array $expectedContent, int $expectedVersion): void
    {
        $stored = self::getContainer()->get(BandSpaceNoteRepository::class)->find($noteId);
        $this->assertSame($expectedContent, $stored?->content);
        $this->assertSame($expectedVersion, $stored?->contentVersion);
    }
}
