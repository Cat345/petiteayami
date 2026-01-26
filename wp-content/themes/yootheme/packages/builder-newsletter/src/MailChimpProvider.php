<?php

namespace YOOtheme\Builder\Newsletter;

use YOOtheme\HttpClientInterface;
use function YOOtheme\trans;

class MailChimpProvider extends AbstractProvider
{
    /**
     * @param string              $apiKey
     * @param HttpClientInterface $client
     *
     * @throws \Exception
     */
    public function __construct(string $apiKey, HttpClientInterface $client)
    {
        parent::__construct($apiKey, $client);

        if (!str_contains($apiKey, '-')) {
            throw new \Exception('Invalid API key.');
        }

        [, $dataCenter] = explode('-', $apiKey);

        $this->apiEndpoint = "https://{$dataCenter}.api.mailchimp.com/3.0";
    }

    /**
     * @inheritdoc
     */
    public function lists(array $provider): array
    {
        $result = $this->get('lists', ['count' => '100']);

        if ($result['success']) {
            $lists = array_map(
                fn($list) => ['value' => $list['id'], 'text' => $list['name']],
                $result['data']['lists'],
            );
        } else {
            throw new \Exception($result['data']);
        }

        return ['lists' => $lists, 'clients' => []];
    }

    /**
     * @inheritdoc
     */
    public function subscribe(string $email, array $data, array $provider): bool
    {
        if (empty($provider['list_id'])) {
            throw new \Exception(trans('No list selected.'));
        }

        $mergeFields = [];
        if (isset($data['first_name'])) {
            $mergeFields['FNAME'] = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $mergeFields['LNAME'] = $data['last_name'];
        }

        // Deprecated
        $provider['double_optin'] ??= true;

        $result = $this->post("lists/{$provider['list_id']}/members", [
            'email_address' => $email,
            'status' => $provider['double_optin'] ? 'pending' : 'subscribed',
            'merge_fields' => $mergeFields,
        ]);

        if (!$result['success']) {
            if (str_contains($result['data'], 'already a list member')) {
                throw new \Exception(
                    trans('%email% is already a list member.', [
                        '%email%' => htmlspecialchars($email),
                    ]),
                );
            }
            if (
                str_contains(
                    $result['data'],
                    'was permanently deleted and cannot be re-imported. The contact must re-subscribe to get back on the list',
                )
            ) {
                throw new \Exception(
                    trans(
                        '%email% was permanently deleted and cannot be re-imported. The contact must re-subscribe to get back on the list.',
                        ['%email%' => htmlspecialchars($email)],
                    ),
                );
            }
            if ($result['data'] === 'Please provide a valid email address.') {
                throw new \Exception(trans('Please provide a valid email address.'));
            }

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
            'Authorization' => "apikey {$this->apiKey}",
        ];
    }
}
