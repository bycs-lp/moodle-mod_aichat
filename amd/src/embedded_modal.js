// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

import * as ReactiveInit from 'block_ai_chat/reactive_init';
import Templates from 'core/templates';

export const init = async(contextid, uniqueId) => {
    // Build chat dialog modal.
    const {html, js} = await Templates.renderForPromise('mod_aichat/embedded_modal', {uniqueId});
    const container = document.querySelector('[data-mod_aichat-element="embeddingmodalcontainer"]');
    Templates.replaceNodeContents(container, html, js);
    await ReactiveInit.init(contextid, `[data-block_aichat-element="mainelement"][data-id="${uniqueId}"]`, null, 'mod_aichat');
};
