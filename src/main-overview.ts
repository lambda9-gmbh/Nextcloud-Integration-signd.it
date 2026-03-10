// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

import { createApp } from 'vue'
import OverviewApp from './views/OverviewApp.vue'

const app = createApp(OverviewApp)
app.mount('#integration-signd-overview')
