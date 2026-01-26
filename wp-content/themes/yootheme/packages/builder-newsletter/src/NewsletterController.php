<?php

namespace YOOtheme\Builder\Newsletter;

use YOOtheme\Http\Request;
use YOOtheme\Http\Response;
use function YOOtheme\app;
use function YOOtheme\trans;

/**
 * @phpstan-type Providers array{mailchimp: string, cmonitor: string}
 * @phpstan-type Data array{name?: string, after_submit: string, message: string, redirect: string, client_id: string, list_id: string, double_optin: bool}
 */
class NewsletterController
{
    /**
     * @var Providers
     */
    protected $providers;

    /**
     * @var string
     */
    protected $secret;

    /**
     * @param Providers $providers
     * @param string $secret
     */
    public function __construct(array $providers, string $secret)
    {
        $this->providers = $providers;
        $this->secret = $secret;
    }

    public function lists(Request $request, Response $response): Response
    {
        $settings = $request->getParam('settings');

        try {
            if (!($provider = $this->getProvider($settings['name'] ?? ''))) {
                $request->abort(400, 'Invalid provider');
            }

            return $response->withJson($provider->lists($settings));
        } catch (\Exception $e) {
            $request->abort(400, $e->getMessage());
        }
    }

    public function subscribe(Request $request, Response $response): Response
    {
        $hash = $request->getQueryParam('hash');
        $settings = $request->getParam('settings');

        $request->abortIf($hash !== $this->getHash($settings), 400, 'Invalid settings hash');

        try {
            $settings = $this->decodeData($settings);

            $request->abortIf(
                !($provider = $this->getProvider($settings['name'] ?? '')),
                400,
                'Invalid provider',
            );

            $provider->subscribe(
                $request->getParam('email'),
                [
                    'first_name' => $request->getParam('first_name', ''),
                    'last_name' => $request->getParam('last_name', ''),
                ],
                $settings,
            );
        } catch (\Exception $e) {
            $request->abort(400, $e->getMessage());
        }

        $return = ['successful' => true];

        if ($settings['after_submit'] === 'redirect') {
            $return['redirect'] = $settings['redirect'];
        } else {
            $return['message'] = trans($settings['message']);
        }

        return $response->withJson($return);
    }

    /**
     * @param Data $data
     */
    public function encodeData(array $data): string
    {
        return base64_encode(json_encode($data));
    }

    /**
     * @return Data
     */
    public function decodeData(string $data): array
    {
        return json_decode(base64_decode($data), true);
    }

    public function getHash(string $data): string
    {
        return hash('fnv132', hash_hmac('sha1', $data, $this->secret));
    }

    protected function getProvider(string $name): ?AbstractProvider
    {
        return isset($this->providers[$name]) ? app($this->providers[$name]) : null;
    }
}
