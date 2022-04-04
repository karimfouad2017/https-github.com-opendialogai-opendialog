<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenDialogAi\AttributeEngine\AttributeBag\BasicAttributeBag;
use OpenDialogAi\AttributeEngine\Contracts\Attribute;
use OpenDialogAi\AttributeEngine\Contracts\AttributeBag;
use OpenDialogAi\ContextEngine\Contexts\User\UserContext;
use OpenDialogAi\ContextEngine\DataClients\UserAttributeContextClient;
use OpenDialogAi\Core\Conversation\DataClients\Serializers\AttributeNormalizer;
use OpenDialogAi\GraphQLClient\GraphQLClientInterface;
use Symfony\Component\Serializer\Serializer;

class MigrateContextDgraphMysql extends Command
{
    protected $signature = 'dgraph_migrate:user-context';

    protected $description = 'Migrates context data from Dgraph to RDBMS';

    private GraphQLClientInterface $graphClient;


    public function handle()
    {
        try {
            $this->graphClient = resolve(GraphQLClientInterface::class);
            $contextClient = resolve(UserAttributeContextClient::class);

            $userIds = $this->getUserIds();
            $count = 0;

            foreach ($userIds as $userId) {
                $data = $this->queryContextsByUser($userId);
                foreach ($data['data']['getUser']['contexts'] as $userAttributes) {
                    $attributeBag = $this->getAttributeBag($userAttributes['attributes']);
                    $contextClient->persistAttributes(UserContext::USER_CONTEXT, $userId, $attributeBag);
                }
                $count++;
            }

            $this->info(sprintf('Migrated %s users with their attributes', $count));
        } catch (\Exception $e) {
            $this->error("Migration error: " . $e->getMessage());
        }

        return 0;
    }

    /**
     * Gets all unique users ids from Dgraph
     *
     * @return array
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    private function getUserIds(): array
    {
        $serializer = new Serializer([new AttributeNormalizer()], []);
        $userIds = [];
        $data = $this->queryUsers();
        foreach ($data['data']['queryContext'] as $userAttributes) {
            $attribute = $serializer->denormalize($userAttributes['attributes'][0], Attribute::class);
            array_push($userIds, $attribute->getValue());
        }
        return $userIds;
    }

    /**
     * Queries for all the unique users in Dgraph.
     *
     * @return array
     */
    private function queryUsers(): array
    {
        $query = <<<'GQL'
            query getUsers($contextId: String!) {
                queryContext {
                        attributes(filter: {name: {eq: $contextId}}) {
                            name
                            type
                            value
                        }
                }
            }
        GQL;

        return $this->graphClient->query($query, ['contextId' => 'user_id']);
    }

    /**
     * Queries all the context for a specific user
     *
     * @param $userId
     * @return array
     */
    private function queryContextsByUser($userId): array
    {
        $query = <<<'GQL'
            query getContexts($userId: String!) {
                getUser(user_id: $userId) {
                    contexts {
                        attributes {
                                name
                                type
                                value
                        }
                    }
                }
            }
        GQL;

        return $this->graphClient->query($query, ['userId' => $userId]);
    }

    /**
     * Creates attribute bag from the data returned from Dgraph.
     *
     * @param $userAttributes
     * @return AttributeBag
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    private function getAttributeBag($userAttributes): AttributeBag
    {
        $serializer = new Serializer([new AttributeNormalizer()], []);
        $attributes = new BasicAttributeBag();

        foreach ($userAttributes as $attributeData) {
            $attribute = $serializer->denormalize($attributeData, Attribute::class);
            $attributes->addAttribute($attribute);
        }

        return $attributes;
    }
}
