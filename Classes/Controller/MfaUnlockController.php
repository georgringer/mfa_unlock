<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace GeorgRinger\MfaUnlock\Controller;

use GeorgRinger\MfaUnlock\Service\MfaUnlockService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\SecurityAspect;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * @internal This class is a specific Backend controller implementation and is not part of the TYPO3's Core API.
 */
#[AsController]
final readonly class MfaUnlockController
{
    public const ROUTE = 'backend_user_mfa';
    public const REQUEST_TOKEN_SCOPE = 'mfa_unlock/unlock';

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private UriBuilder $uriBuilder,
        private MfaUnlockService $mfaUnlockService,
        private Context $context,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
        $view = $this->moduleTemplateFactory->create($request);
        $route = (string)$this->uriBuilder->buildUriFromRoute(self::ROUTE);

        if ($request->getMethod() === 'POST') {
            $this->handleUnlock($request, $view, $languageService);
            return new RedirectResponse($route);
        }

        $view->setTitle($languageService->sL('mfa_unlock.messages:title'));
        $view->assignMultiple([
            'lockedUsers' => $this->mfaUnlockService->findLockedUsers(),
            'actionUri' => $route,
            'requestTokenName' => RequestToken::PARAM_NAME,
            'requestTokenValue' => RequestToken::create(self::REQUEST_TOKEN_SCOPE)
                ->toHashSignedJwt(SecurityAspect::provideIn($this->context)->provideNonce()),
        ]);
        return $view->renderResponse('Backend/Index');
    }

    private function handleUnlock(ServerRequestInterface $request, ModuleTemplate $view, LanguageService $languageService): void
    {
        $requestToken = SecurityAspect::provideIn($this->context)->getReceivedRequestToken();
        if (!$requestToken instanceof RequestToken || $requestToken->scope !== self::REQUEST_TOKEN_SCOPE) {
            $view->addFlashMessage(
                $languageService->sL('mfa_unlock.messages:flash.invalidToken.message'),
                $languageService->sL('mfa_unlock.messages:flash.invalidToken.title'),
                ContextualFeedbackSeverity::ERROR
            );
            return;
        }

        $body = (array)$request->getParsedBody();
        if (($body['all'] ?? '') !== '') {
            $count = $this->mfaUnlockService->unlockAll();
        } else {
            $row = $this->mfaUnlockService->findUser((string)(int)($body['user'] ?? 0));
            $count = $row !== null && $this->mfaUnlockService->unlock($row) !== [] ? 1 : 0;
        }

        if ($count === 0) {
            $view->addFlashMessage(
                $languageService->sL('mfa_unlock.messages:flash.nothing.message'),
                $languageService->sL('mfa_unlock.messages:flash.nothing.title'),
                ContextualFeedbackSeverity::INFO
            );
            return;
        }
        $view->addFlashMessage(
            sprintf($languageService->sL('mfa_unlock.messages:flash.unlocked.message'), $count),
            $languageService->sL('mfa_unlock.messages:flash.unlocked.title'),
            ContextualFeedbackSeverity::OK
        );
    }
}
