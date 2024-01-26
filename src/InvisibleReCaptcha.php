<?php

namespace SpaanProductions\InvisibleReCaptcha;

use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Client;

class InvisibleReCaptcha
{
    const API_URI = 'https://www.google.com/recaptcha/api.js';
    const VERIFY_URI = 'https://www.google.com/recaptcha/api/siteverify';
    const POLYFILL_URI = 'https://cdn.polyfill.io/v2/polyfill.min.js';
    const DEBUG_ELEMENTS = [
        '_submitForm',
        '_captchaForm',
        '_captchaSubmit'
    ];

    /** The reCaptcha site key. */
    protected string $siteKey;

    /** The reCaptcha secret key. */
    protected string $secretKey;

    /** The other config options. */
    protected array $options;

    protected Client $client;

    /**
     * InvisibleReCaptcha.
     *
     * @param string $secretKey
     * @param string $siteKey
     * @param array $options
     */
    public function __construct(string $siteKey, string $secretKey, array $options = [])
    {
        $this->siteKey = $siteKey;
        $this->secretKey = $secretKey;
        $this->setOptions($options);
        $this->setClient(
            new Client([
                'timeout' => $this->getOption('timeout', 5)
            ])
        );
    }

    /**
     * Get reCaptcha js by optional language param.
     */
    public function getCaptchaJs(?string $lang = null): string
    {
        return $lang ? static::API_URI . '?hl=' . $lang : static::API_URI;
    }

    /**
     * Get polyfill js
     */
    public function getPolyfillJs(): string
    {
        return static::POLYFILL_URI;
    }

    /**
     * Render HTML reCaptcha by optional language param.
     */
    public function render(?string $lang = null, ?string $nonce = null): string
    {
        $html = $this->renderPolyfill();
        $html .= $this->renderCaptchaHTML();
        $html .= $this->renderFooterJS($lang, $nonce);
        return $html;
    }

    /**
     * Render HTML reCaptcha from directive.
     */
    public function renderCaptcha(...$arguments): string
    {
        return $this->render(...$arguments);
    }

    /**
     * Render the polyfill JS components only.
     */
    public function renderPolyfill(): string
    {
        return '<script src="' . $this->getPolyfillJs() . '"></script>' . PHP_EOL;
    }

    /**
     * Render the captcha HTML.
     */
    public function renderCaptchaHTML(): string
    {
        $html = '<div id="_g-recaptcha"></div>' . PHP_EOL;
        if ($this->getOption('hideBadge', false)) {
            $html .= '<style>.grecaptcha-badge{display:none !important;}</style>' . PHP_EOL;
        }

        $html .= '<div class="g-recaptcha" data-sitekey="' . $this->siteKey .'" ';
        $html .= 'data-size="invisible" data-callback="_submitForm" data-badge="' . $this->getOption('dataBadge', 'bottomright') . '"></div>';

        return $html;
    }

    /**
     * Render the footer JS necessary for the recaptcha integration.
     */
    public function renderFooterJS(...$arguments): string
    {
        $lang = Arr::get($arguments, 0);
        $nonce = Arr::get($arguments, 1);

        $html = '<script src="' . $this->getCaptchaJs($lang) . '" async defer';
        if (isset($nonce) && ! empty($nonce)) {
            $html .= ' nonce="' . $nonce . '"';
        }
        $html .= '></script>' . PHP_EOL;
        $html .= '<script>var _submitForm,_captchaForm,_captchaSubmit,_execute=true,_captchaBadge;</script>';
        $html .= "<script>window.addEventListener('load', _loadCaptcha);" . PHP_EOL;
        $html .= "function _loadCaptcha(){";
        if ($this->getOption('hideBadge', false)) {
            $html .= "_captchaBadge=document.querySelector('.grecaptcha-badge');";
            $html .= "if(_captchaBadge){_captchaBadge.style = 'display:none !important;';}" . PHP_EOL;
        }
        $html .= '_captchaForm=document.querySelector("#_g-recaptcha").closest("form");';
        $html .= "_captchaSubmit=_captchaForm.querySelector('[type=submit]');";
        $html .= '_submitForm=function(){if(typeof _submitEvent==="function"){_submitEvent();';
        $html .= 'grecaptcha.reset();}else{_captchaForm.submit();}};';
        $html .= "_captchaForm.addEventListener('submit',";
        $html .= "function(e){e.preventDefault();if(typeof _beforeSubmit==='function'){";
        $html .= "_execute=_beforeSubmit(e);}if(_execute){grecaptcha.execute();}});";
        if ($this->getOption('debug', false)) {
            $html .= $this->renderDebug();
        }
        $html .= "}</script>" . PHP_EOL;
        return $html;
    }

    /**
     * Get debug javascript code.
     */
    public function renderDebug(): string
    {
        $html = '';
        foreach (static::DEBUG_ELEMENTS as $element) {
            $html .= $this->consoleLog('"Checking element binding of ' . $element . '..."');
            $html .= $this->consoleLog($element . '!==undefined');
        }

        return $html;
    }

    /**
     * Get console.log function for javascript code.
     */
    public function consoleLog($string): string
    {
        return "console.log({$string});";
    }

    /**
     * Verify invisible reCaptcha response.
     */
    public function verifyResponse(string $response, string $clientIp): bool
    {
        if (empty($response)) {
            return false;
        }

        $response = $this->sendVerifyRequest([
            'secret' => $this->secretKey,
            'remoteip' => $clientIp,
            'response' => $response
        ]);

        return isset($response['success']) && $response['success'] === true;
    }

    /**
     * Verify invisible reCaptcha response by Symfony Request.
     */
    public function verifyRequest(Request $request): bool
    {
        return $this->verifyResponse(
            $request->get('g-recaptcha-response'),
            $request->getClientIp()
        );
    }

    /**
     * Send verify request.
     */
    protected function sendVerifyRequest(array $query = []): array
    {
        $response = $this->client->post(static::VERIFY_URI, [
            'form_params' => $query,
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Getter function of site key
     */
    public function getSiteKey(): string
    {
        return $this->siteKey;
    }

    /**
     * Getter function of secret key
     */
    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    /**
     * Set options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * Set option
     */
    public function setOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    }

    /**
     * Getter function of options
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get default option value for options.
     */
    public function getOption(string $key, ?string $value = null): mixed
    {
        return array_key_exists($key, $this->options) ? $this->options[$key] : $value;
    }

    /**
     * Set guzzle client
     */
    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * Getter function of guzzle client
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}
