/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const path = require('node:path')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const webpackRules = require('@nextcloud/webpack-vue-config/rules')

// Type checking runs separately through vue-tsc. Keeping the build loader in
// transpile-only mode prevents test-only Vite configuration from entering the
// browser dependency graph.
webpackRules.RULE_TS.use = [
	'babel-loader',
	{
		loader: 'ts-loader',
		options: { transpileOnly: true },
	},
]
webpackConfig.module.rules = Object.values(webpackRules)

webpackConfig.entry = {
	main: path.join(__dirname, 'src', 'main.ts'),
}

module.exports = webpackConfig
