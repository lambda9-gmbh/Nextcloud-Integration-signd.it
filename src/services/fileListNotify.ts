// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

import { getClient, getDefaultPropfind, getRootPath, resultToNode } from '@nextcloud/files/dav'
import { emit } from '@nextcloud/event-bus'

interface DownloadResult {
	path: string
	name?: string
	fileId?: number
	size?: number
	mtime?: number
	owner?: string
}

/**
 * Notify the Files app that a new file was created.
 * Fetches the file node via WebDAV and emits 'files:node:created'
 * so the file list updates without a page reload.
 */
export async function notifyFileCreated(result: DownloadResult): Promise<void> {
	if (!result.name || !result.owner) return

	try {
		const rootPath = getRootPath()

		// Extract relative path from NC internal path
		// e.g. "/admin/files/Documents/file.pdf" → "/Documents/file.pdf"
		const internalPrefix = `/${result.owner}/files`
		let relativePath: string
		if (result.path.startsWith(internalPrefix)) {
			relativePath = result.path.slice(internalPrefix.length)
		} else {
			relativePath = result.path
		}

		const client = getClient()
		const stat = await client.stat(rootPath + relativePath, {
			details: true,
			data: getDefaultPropfind(),
		}) as { data: Parameters<typeof resultToNode>[0] }

		const node = resultToNode(stat.data)
		emit('files:node:created', node)
	} catch (e) {
		console.warn('[signd] Failed to notify file list about new file', e)
	}
}
