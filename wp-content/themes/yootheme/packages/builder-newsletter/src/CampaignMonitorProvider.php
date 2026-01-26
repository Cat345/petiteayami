<?php

namespace YOOtheme\Builder\Newsletter;

class CampaignMonitorProvider extends AbstractProvider
{
    protected string $apiEndpoint = 'https://api.createsend.com/api/v3.1';

    /**
     * @inheritdoc
     */
    public function lists(array $provider): array
    {
        $result = $this->get('clients.json');

        if (!$result['success']) {
            throw new \Exception($result['data']);
        }

        $clients = array_map(
            fn($client) => ['value' => $client['ClientID'], 'text' => $client['Name']],
            $result['data'],
        );

        if (!($clientId = $provider['client_id'] ?: $clients[0]['value'])) {
            throw new \Exception('Invalid client id.');
        }

        $result = $this->get("/clients/{$clientId}/lists.json");
        if (!$result['success']) {
            throw new \Exception($result['data']);
        }

        $lists = array_map(
            fn($list) => ['value' => $list['ListID'], 'text' => $list['Name']],
            $result['data'],
        );

        return ['clients' => $clients, 'lists' => $lists];
    }

    /**
     * @inheritdoc
     */
    public function subscribe(string $email, array $data, array $provider): bool
    {
        if (empty($provider['list_id'])) {
            throw new \Exception('No list selected.');
        }

        $name = (!empty($data['first_name']) ? $data['first_name'] . ' ' : '') . $data['last_name'];
        $result = $this->post("subscribers/{$provider['list_id']}.json", [
            'EmailAddress' => $email,
            'Name' => $name,
            'Resubscribe' => true,
            'RestartSubscriptionBasedAutoresponders' => true,
        ]);

        if (!$result['success']) {
            throw new \Exception($result['data']);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function getHeaders(): array
    {
        return parent::getHeaders() + [
            'Authorization' => 'Basic ' . base64_encode("{$this->apiKey}:nopass"),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function findError($response, $formattedResponse): string
    {
        return isset($formattedResponse['Message'])
            ? sprintf('%d: %s', $formattedResponse['Code'], $formattedResponse['Message'])
            : parent::findError($response, $formattedResponse);
    }
}
