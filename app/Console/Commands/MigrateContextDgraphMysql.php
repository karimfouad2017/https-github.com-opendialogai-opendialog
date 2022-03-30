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
            $data = $this->queryContexts();

            $contextClient = resolve(UserAttributeContextClient::class);
            $count = 0;
            foreach ($data['data']['queryContext'] as $userAttributes) {
                $attributeBag = $this->getAttributeBag($userAttributes['attributes']);
                $userId = $this->getUserId($attributeBag);
                if (!$userId) {
                    $this->warn('Found attributes without user id. Cannot migrate. Attributes', $userAttributes);
                    continue;
                }
                $contextClient->persistAttributes(UserContext::USER_CONTEXT, $userId, $attributeBag);
                $count++;
            }

            $this->info(sprintf('Migrated %s users with their attributes', $count));
        } catch (\Exception $e) {
            $this->error("Migration error: " . $e->getMessage());
        }

        return 0;
    }

    /**
     * Queries all contexts, from all users, from dgraph.
     * @return array
     */
    private function queryContexts(): array
    {
        $query = <<<'GQL'
            query getContexts {
                queryContext {
                    attributes {
                            name
                            type
                            value
                    }
            }
        }
        GQL;

        return $this->graphClient->query($query);
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

    /**
     * Finds the attribute with the user_id from the bag
     *
     * @param AttributeBag $attributeBag
     * @return string|null
     */
    private function getUserId(AttributeBag $attributeBag): ?string
    {
        foreach ($attributeBag->getAttributes() as $singleAttribute) {
            if ($singleAttribute->getId() == 'user_id') {
                return $singleAttribute->getValue();
            }
        }
        return null;
    }
}
