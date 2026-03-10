// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

import { FileAction, registerFileAction } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import svgIcon from '../img/app.svg?raw'

// Register File Action for PDFs
registerFileAction(
    new FileAction({
        id: 'integration-signd-sign-file',
        displayName: () => t('integration_signd', 'Digitally sign'),
        iconSvgInline: () => svgIcon,
        order: 25,

        enabled(nodes) {
            return nodes.length === 1 && nodes[0].mime === 'application/pdf'
        },

        async exec(node) {
            // Open sidebar and activate signd tab
            if (OCA?.Files?.Sidebar) {
                await OCA.Files.Sidebar.open(node.path)
                OCA.Files.Sidebar.setActiveTab('integration-signd')
            }
            return null
        },
    }),
)

// Register Sidebar Tab (Legacy API for NC 30-32)
window.addEventListener('DOMContentLoaded', () => {
    if (!OCA?.Files?.Sidebar) {
        return
    }

    let tabInstance: any = null

    const signdTab = new OCA.Files.Sidebar.Tab({
        id: 'integration-signd',
        name: t('integration_signd', 'signd.it'),
        icon: 'icon-rename',

        enabled(fileInfo: any) {
            return fileInfo?.mimetype === 'application/pdf'
        },

        async mount(el: HTMLElement, fileInfo: any, context: any) {
            // Dynamically import to keep initial bundle small
            const { createApp } = await import('vue')
            const { default: SigndSidebarTab } = await import('./views/SigndSidebarTab.vue')

            if (tabInstance) {
                tabInstance.unmount()
            }

            tabInstance = createApp(SigndSidebarTab, {
                fileInfo,
            })
            tabInstance.mount(el)
        },

        update(fileInfo: any) {
            // Re-mount with new fileInfo
            if (tabInstance) {
                // For simplicity, destroy and re-create
                // The component will re-fetch data based on new fileInfo
                const el = tabInstance._container
                if (el) {
                    tabInstance.unmount()
                    import('vue').then(({ createApp }) => {
                        import('./views/SigndSidebarTab.vue').then(({ default: SigndSidebarTab }) => {
                            tabInstance = createApp(SigndSidebarTab, {
                                fileInfo,
                            })
                            tabInstance.mount(el)
                        })
                    })
                }
            }
        },

        destroy() {
            if (tabInstance) {
                tabInstance.unmount()
                tabInstance = null
            }
        },
    })

    OCA.Files.Sidebar.registerTab(signdTab)
})

// TypeScript declarations for Nextcloud globals
declare global {
    interface Window {
        OCA: any
    }
    const OCA: any
}
