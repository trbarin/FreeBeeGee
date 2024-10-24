/**
 * @file Send keyboard/shortcut events to the proper functions.
 * @module
 * @copyright 2021-2023 Markus Leupold-Löwenthal
 * @license AGPL-3.0-or-later
 *
 * This file is part of FreeBeeGee.
 *
 * FreeBeeGee is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * FreeBeeGee is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU Affero General Public License for details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with FreeBeeGee. If not, see <https://www.gnu.org/licenses/>.
 */

import _ from 'src/js/lib/FreeDOM.mjs'
import * as Browser from 'src/js/lib/util-browser.mjs'
import * as Content from 'src/js/view/room/tabletop/content.mjs'
import * as Dom from 'src/js/view/room/tabletop/dom.mjs'
import * as Event from 'src/js/lib/event.mjs'
import * as Modal from 'src/js/view/room/modal.mjs'
import * as ModalHelp from 'src/js/view/room/modal/help.mjs'
import * as ModalSettings from 'src/js/view/room/modal/settings.mjs'
import * as Mouse from 'src/js/view/room/mouse/index.mjs'
import * as Room from 'src/js/view/room/index.mjs'
import * as State from 'src/js/state/index.mjs'
import * as Sync from 'src/js/view/room/sync.mjs'
import * as Window from 'src/js/view/room/window.mjs'

// -----------------------------------------------------------------------------

/** register the keyboard handler on document load */
document.addEventListener('keydown', keydown => handleRoomKeys(keydown))

// -----------------------------------------------------------------------------

/**
 * Call proper functions after certain keys are pressed.
 *
 * @param {KeyboardEvent} keydown The triggering event.
 */
function handleRoomKeys (keydown) {
  if (!_('#tabletop').exists()) return

  Sync.touch()

  if (keydown.key === 'Escape') { // close modals on ESC
    if (Modal.isOpen()) {
      Modal.close()
      keydown.stopPropagation()
      return
    } else if (Window.isOpen()) {
      Window.close()
      keydown.stopPropagation()
      return
    }
  }

  if (Mouse.isDragging() && !Modal.isOpen() && !Window.isOpen()) { // keys that work while dragging
    if (Room.getMode().keydrag(keydown)) {
      keydown.stopPropagation()
      keydown.preventDefault()
      return
    }
  }

  if (!Mouse.isDragging() && !Modal.isOpen() && Window.isOpen()) { // keys for the library window
    switch (keydown.key) {
      case 'e': // edit
      case 'F2':
        Event.trigger(Event.HOOK.LIBRARY_EDIT)
        break
    }
    return
  }

  if (!Mouse.isDragging() && !Modal.isOpen() && !Window.isOpen()) { // keys that don't work while dragging
    if (keydown.repeat) { // prevent key-repeat on held keys
      keydown.stopPropagation()
      keydown.preventDefault()
      return
    }

    switch (keydown.key) {
      case '1': // toggle layer, switch table
        if (keydown.ctrlKey | keydown.altKey) State.setTableNo(1); else Room.toggleLayer(Content.LAYER.OTHER)
        break
      case '2': // toggle layer, switch table
        if (keydown.ctrlKey | keydown.altKey) State.setTableNo(2); else Room.toggleLayer(Content.LAYER.TOKEN)
        break
      case '3': // toggle layer, switch table
        if (keydown.ctrlKey | keydown.altKey) State.setTableNo(3); else Room.toggleLayer(Content.LAYER.STICKER)
        break
      case '4': // toggle layer, switch table
        if (keydown.ctrlKey | keydown.altKey) State.setTableNo(4); else Room.toggleLayer(Content.LAYER.TILE)
        break
      case '5': // toggle layer, switch table
        if (keydown.ctrlKey | keydown.altKey) State.setTableNo(5)
        break
      case '6': // toggle layer, switch table
        if (keydown.ctrlKey | keydown.altKey) State.setTableNo(6)
        break
      case '7': // toggle layer, switch table
        if (keydown.ctrlKey | keydown.altKey) State.setTableNo(7)
        break
      case '8': // toggle layer, switch table
        if (keydown.ctrlKey | keydown.altKey) State.setTableNo(8)
        break
      case '9': // toggle layer, switch table
        if (keydown.ctrlKey | keydown.altKey) State.setTableNo(9)
        break
      case 'F11':
        Browser.toggleFullscreen()
        break
      case 'G': // grid (table)
        Room.toggleGrid()
        break
      case 'u': // undo
      case 'z': // undo
        if (keydown.ctrlKey) State.undo()
        break
      case 'Undo': // dedicated undo key
        State.undo()
        break
      case 'h': // help
      case 'H':
      case '?':
      case 'F1':
      case 'Help':
        ModalHelp.open()
        break
      case 'S': // settings
        ModalSettings.open()
        break
      case '+': // zoom in
      case '=':
      case 'ZoomIn':
        Dom.zoom(1)
        break
      case '-': // zoom out
      case 'ZoomOut':
        Dom.zoom(-1)
        break
      default:
        if (!Room.getMode().keydown(keydown)) return
    }
    keydown.stopPropagation()
    keydown.preventDefault()
  }
}
