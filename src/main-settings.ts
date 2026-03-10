// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

import { createApp } from 'vue'
import AdminSettings from './settings/AdminSettings.vue'

const app = createApp(AdminSettings)
app.mount('#integration-signd-admin-settings')
