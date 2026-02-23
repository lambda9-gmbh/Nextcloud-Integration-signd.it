import { davGetClient, davGetDefaultPropfind, davGetRootPath, davResultToNode } from '@nextcloud/files'
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
		const rootPath = davGetRootPath()

		// Extract relative path from NC internal path
		// e.g. "/admin/files/Documents/file.pdf" → "/Documents/file.pdf"
		const internalPrefix = `/${result.owner}/files`
		let relativePath: string
		if (result.path.startsWith(internalPrefix)) {
			relativePath = result.path.slice(internalPrefix.length)
		} else {
			relativePath = result.path
		}

		const client = davGetClient()
		const stat = await client.stat(rootPath + relativePath, {
			details: true,
			data: davGetDefaultPropfind(),
		}) as { data: Parameters<typeof davResultToNode>[0] }

		const node = davResultToNode(stat.data)
		emit('files:node:created', node)
	} catch (e) {
		console.warn('[signd] Failed to notify file list about new file', e)
	}
}