<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Entity\BandSpace\TechRiderItem;
use App\Enum\BandSpace\TechRiderItemType;
use App\Repository\BandSpace\TechRiderItemRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\BandSpace\TechRiderItemFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\BandSpace\TechRider\TechRiderItemContent;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A rider text item is held to the rich text format the web editor produces (#1012).
 */
#[ResetDatabase]
class TechRiderItemContentFormatTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
    private const array PATCH_HEADERS = ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    private const array STORED_CONTENT = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Deux wedges']]]]];

    private const array LEVEL_ONE_HEADING = ['type' => 'doc', 'content' => [['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Son']]]]];

    public function test_creating_a_text_item_with_a_level_one_heading_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', $this->itemsUrl($bandSpace->id, $rider->id), ['title' => 'Son', 'content' => self::LEVEL_ONE_HEADING], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderItemContent::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Les titres doivent être de niveau 2 ou 3',
                    'code' => TechRiderItemContent::ERROR_CODE,
                ],
            ],
            'detail' => 'content: Les titres doivent être de niveau 2 ou 3',
            'type' => '/validation_errors/' . TechRiderItemContent::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'content: Les titres doivent être de niveau 2 ou 3',
        ]);
        $this->assertSame([], self::getContainer()->get(TechRiderItemRepository::class)->findBy(['techRider' => $rider->id]));
    }

    /** Checklists are a note's only (#1175): a rider text is sent to a venue as a PDF. */
    public function test_creating_a_text_item_with_a_checklist_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $checklist = ['type' => 'doc', 'content' => [['type' => 'taskList', 'content' => [
            ['type' => 'taskItem', 'attrs' => ['checked' => false], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Wedges']]]]],
        ]]]];

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', $this->itemsUrl($bandSpace->id, $rider->id), ['title' => 'Son', 'content' => $checklist], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderItemContent::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Le contenu contient un élément non pris en charge (« taskList »)',
                    'code' => TechRiderItemContent::ERROR_CODE,
                ],
            ],
            'detail' => 'content: Le contenu contient un élément non pris en charge (« taskList »)',
            'type' => '/validation_errors/' . TechRiderItemContent::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'content: Le contenu contient un élément non pris en charge (« taskList »)',
        ]);
        $this->assertSame([], self::getContainer()->get(TechRiderItemRepository::class)->findBy(['techRider' => $rider->id]));
    }

    public function test_a_colour_off_the_palette_is_refused_and_the_item_is_unchanged(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seedTextItem();
        $content = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Rouge', 'marks' => [['type' => 'textStyle', 'attrs' => ['color' => '#ff00ff']]]],
        ]]]];

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', $this->itemsUrl($bandSpace->id, $rider->id) . '/' . $item->id, ['content' => $content], self::PATCH_HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderItemContent::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Le contenu contient une couleur hors de la palette',
                    'code' => TechRiderItemContent::ERROR_CODE,
                ],
            ],
            'detail' => 'content: Le contenu contient une couleur hors de la palette',
            'type' => '/validation_errors/' . TechRiderItemContent::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'content: Le contenu contient une couleur hors de la palette',
        ]);
        $this->assertStoredItem($item, TechRiderItemType::Text, self::STORED_CONTENT);
    }

    /**
     * A contacts item's content is not prose, so the check is chosen by the item's type. That type
     * is the stored one: a PATCH body cannot relabel a text item to slip a document past it.
     */
    public function test_a_patch_body_cannot_relabel_a_text_item_to_skip_the_check(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seedTextItem();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->itemsUrl($bandSpace->id, $rider->id) . '/' . $item->id,
            ['type' => 'contacts', 'content' => self::LEVEL_ONE_HEADING],
            self::PATCH_HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderItemContent::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Les titres doivent être de niveau 2 ou 3',
                    'code' => TechRiderItemContent::ERROR_CODE,
                ],
            ],
            'detail' => 'content: Les titres doivent être de niveau 2 ou 3',
            'type' => '/validation_errors/' . TechRiderItemContent::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'content: Les titres doivent être de niveau 2 ou 3',
        ]);
        $this->assertStoredItem($item, TechRiderItemType::Text, self::STORED_CONTENT);
    }

    /** A contacts item keeps its presentation choices in the same field, which is not a document. */
    public function test_a_contacts_item_content_is_not_held_to_the_format(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $item = TechRiderItemFactory::new(['techRider' => $rider, 'type' => TechRiderItemType::Contacts, 'title' => 'Membres et contacts', 'content' => null])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', $this->itemsUrl($bandSpace->id, $rider->id) . '/' . $item->id, ['content' => ['showEmails' => true]], self::PATCH_HEADERS);

        $this->assertResponseIsSuccessful();
        $stored = self::getContainer()->get(TechRiderItemRepository::class)->find($item->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderItem',
            '@id' => $this->itemsUrl($bandSpace->id, $rider->id) . '/' . $item->id,
            '@type' => 'TechRiderItem',
            'id' => (string) $item->id,
            'band_space_id' => (string) $bandSpace->id,
            'rider_id' => (string) $rider->id,
            'type' => 'contacts',
            'is_included' => true,
            'is_empty' => false,
            'title' => 'Membres et contacts',
            'content' => ['showEmails' => true],
            'file' => null,
            'patch_list' => null,
            'contacts' => [
                'show_emails' => true,
                'lines' => [$user->username],
                // Only members who opted in on their membership are listed.
                'emails' => [],
            ],
            'position' => 0,
            'creation_datetime' => $stored->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $stored->updateDatetime?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /** @return array{0: \App\Entity\User, 1: \App\Entity\BandSpace\BandSpace, 2: \App\Entity\BandSpace\TechRider, 3: TechRiderItem} */
    private function seedTextItem(): array
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $item = TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Sonorisation', 'content' => self::STORED_CONTENT])->create();

        return [$user, $bandSpace, $rider, $item];
    }

    private function assertStoredItem(TechRiderItem $item, TechRiderItemType $expectedType, array $expectedContent): void
    {
        $stored = self::getContainer()->get(TechRiderItemRepository::class)->find($item->id);
        $this->assertSame($expectedType, $stored?->type);
        $this->assertSame($expectedContent, $stored?->content);
    }

    private function itemsUrl(string $bandSpaceId, string $riderId): string
    {
        return '/api/band_spaces/' . $bandSpaceId . '/tech_riders/' . $riderId . '/items';
    }
}
