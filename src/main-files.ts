// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

import { registerFileAction, getSidebar } from '@nextcloud/files'
import type { IFileAction } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import svgIcon from '../img/app.svg?raw'

// Register File Action for PDFs (NC 33+ plain object API)
const signdAction: IFileAction = {
    id: 'integration-signd-sign-file',
    displayName: () => t('integration_signd', 'Digitally sign'),
    iconSvgInline: () => svgIcon,
    order: 25,

    enabled({ nodes }) {
        return nodes.length === 1 && nodes[0].mime === 'application/pdf'
    },

    async exec({ nodes }) {
        const sidebar = getSidebar()
        sidebar.open(nodes[0], 'integration-signd')
        return null
    },
}

registerFileAction(signdAction)

// Register Sidebar Tab as Web Component (NC 33+ API)
getSidebar().registerTab({
    id: 'integration-signd',
    displayName: t('integration_signd', 'signd.it'),
    iconSvgInline: svgIcon,
    order: 100,
    tagName: 'integration-signd-sidebar-tab',

    enabled({ node }) {
        return node.mime === 'application/pdf'
    },

    async onInit() {
        const { defineCustomElement } = await import('vue')
        const { default: SigndSidebarTab } = await import('./views/SigndSidebarTab.vue')
        const SigndSidebarTabElement = defineCustomElement(SigndSidebarTab, {
            shadowRoot: false,
        })
        customElements.define('integration-signd-sidebar-tab', SigndSidebarTabElement)
    },
})