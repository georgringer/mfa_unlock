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

namespace GeorgRinger\MfaUnlock\EventListener;

use GeorgRinger\MfaUnlock\Controller\MfaUnlockController;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDownButton;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Adds the MFA view to the dropdown of the backend user module. The dropdown is built by the
 * module itself, so the entry is added to the rendered button.
 */
final readonly class AddMfaViewToModuleMenu
{
    private const USER_MODULE = 'backend_user_management';

    public function __construct(
        private UriBuilder $uriBuilder,
        private ComponentFactory $componentFactory,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    #[AsEventListener]
    public function __invoke(ModifyButtonBarEvent $event): void
    {
        $module = $event->getRequest()->getAttribute('module');
        if (!$module instanceof ModuleInterface) {
            return;
        }
        $isMfaView = $module->getIdentifier() === MfaUnlockController::ROUTE;
        if (!$isMfaView && $module->getIdentifier() !== self::USER_MODULE) {
            return;
        }

        $buttons = $event->getButtons();
        $dropdown = null;
        foreach ($buttons[ButtonBar::BUTTON_POSITION_LEFT] ?? [] as $group) {
            foreach ($group as $button) {
                if ($button instanceof DropDownButton) {
                    $dropdown = $button;
                    break 2;
                }
            }
        }
        $languageService = $this->languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
        if ($dropdown === null) {
            if (!$isMfaView) {
                return;
            }
            $dropdown = $this->createUserModuleDropdown($languageService);
            $buttons[ButtonBar::BUTTON_POSITION_LEFT][0][] = $dropdown;
        }

        $dropdown->addItem(
            $this->componentFactory->createDropDownRadio()
                ->setHref((string)$this->uriBuilder->buildUriFromRoute(MfaUnlockController::ROUTE))
                ->setLabel($languageService->sL('mfa_unlock.messages:dropdown.label'))
                ->setActive($isMfaView)
        );
        $event->setButtons($buttons);
    }

    /**
     * The MFA view is a module of its own and has no menu, so it gets the same dropdown the
     * backend user module builds.
     */
    private function createUserModuleDropdown(LanguageService $languageService): DropDownButton
    {
        $dropdown = $this->componentFactory->createDropDownButton()
            ->setLabel($languageService->sL('backend.messages:moduleMenu.dropdown.label'))
            ->setShowActiveLabelText(true)
            ->setShowLabelText(true);
        $views = [
            'backendUsers' => 'list',
            'backendUserGroupsMenu' => 'groups',
            'onlineUsers' => 'online',
            'filemounts' => 'filemounts',
        ];
        foreach ($views as $label => $action) {
            $dropdown->addItem(
                $this->componentFactory->createDropDownRadio()
                    ->setHref((string)$this->uriBuilder->buildUriFromRoute(self::USER_MODULE . '.BackendUser_' . $action))
                    ->setLabel($languageService->sL('LLL:EXT:beuser/Resources/Private/Language/locallang.xlf:' . $label))
                    ->setActive(false)
            );
        }
        return $dropdown;
    }
}
