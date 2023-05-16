$(() => {
  function _sfc32(a, b, c, d) {
    return function (maxVal) {
      a >>>= 0;
      b >>>= 0;
      c >>>= 0;
      d >>>= 0;
      var t = (a + b) | 0;
      a = b ^ (b >>> 9);
      b = (c + (c << 3)) | 0;
      c = (c << 21) | (c >>> 11);
      d = (d + 1) | 0;
      t = (t + d) | 0;
      c = (c + t) | 0;
      return Math.round(((t >>> 0) / 4294967296) * maxVal);
    };
  }

  class Memory {
    constructor(seed) {
      this.seed = seed;
      this.buffer = new Uint16Array(32768);
      const rg = _sfc32(this.seed, 65536, 42, 1);
      for (let i = 0; i < this.buffer.length; ++i) {
        this.buffer[i] = rg(32767) * 2; // all ls-bits must be zero (1 = valid page entry)
      }
    }

    getWord(offset) {
      offset = Math.floor(offset / 2);
      return this.buffer[offset] || 0;
    }

    setWord(offset, value) {
      offset = Math.floor(offset / 2);
      this.buffer[offset] = value;
    }

    /**
     * Fill data passed from server.
     * @param {string} data base64 encoded JSON, which is an object offset => array of (16bit) values
     */
    loadBase64Data(data) {
      const json = JSON.parse(atob(data));
      Object.keys(json).forEach((offsetRaw) => {
        let offset = parseInt(offsetRaw);
        if (
          typeof offset === "number" &&
          !isNaN(offset) &&
          offset >= 0 &&
          offset < 65536
        ) {
          json[offsetRaw].forEach((value) => {
            value = parseInt(value);
            this.setWord(offset, value);
            if (isNaN(value)) {
              console.error(`Invalid value at address ${offset}.`);
            }
            offset += 2;
          });
        } else {
          console.error(`Offset '${offsetRaw}' is not a number.`);
        }
      });
    }
  }

  /*
   * UI
   */

  function _tr(cells) {
    const tr = $("<tr></tr>");
    cells.forEach((cell) => tr.append(cell));
    return tr;
  }

  function _cell(tag, content, cssClass = "", attrs = {}) {
    const cell = $(`<${tag} class="${cssClass}"></${tag}>`);
    if (typeof content === "object") {
      cell.append(content);
    } else {
      cell.text(content);
    }

    Object.keys(attrs).forEach((a) => {
      cell.attr(a, attrs[a]);
    });
    return cell;
  }

  function _th(content, cssClass = "", attrs = {}) {
    return _cell("th", content, cssClass, attrs);
  }

  function _td(content, cssClass = "", attrs = {}) {
    return _cell("td", content, cssClass, attrs);
  }

  function _initTable(table) {
    table.empty();
    const headCells = [_th()];
    for (let i = 0; i < 16; ++i) {
      headCells.push(_th(`+0x${i.toString(16)}`));
    }
    table.append(_tr(headCells));

    const inputTh = _th("0x", "text-end");
    inputTh.append($('<input type="text" maxlength="3" value="000">'));
    inputTh.append("0");

    for (let i = 0; i < 4; ++i) {
      const tr = _tr([i ? _th("", "text-end") : inputTh]);
      tr.attr("data-offset", (i * 16).toString());
      table.append(tr);
    }
  }

  function _updateTable(table, memory, baseOffset) {
    table.find("td").remove();

    table.find("tr[data-offset]").each(function () {
      const offset = parseInt(this.dataset.offset);
      if (isNaN(offset)) return;

      // fill cells
      for (let i = 0; i < 16; i += 2) {
        const text =
          "0x" +
          memory
            .getWord(baseOffset + offset + i)
            .toString(16)
            .padStart(4, "0");
        $(this).append(_td(text, "", { colspan: 2 }));
        memory.getWord(baseOffset + offset + i);
      }

      // update address
      if (offset > 0) {
        $(this)
          .find("th")
          .text(`0x${(baseOffset + offset).toString(16).padStart(4, "0")}`);
      }
    });
  }

  $("table[data-memory-view]").each(function () {
    const table = $(this);
    _initTable(table);

    const memory = new Memory(this.dataset.memoryViewSeed);
    memory.loadBase64Data(this.dataset.memoryView);
    _updateTable(table, memory, 0);

    let currentOffset = 0;
    const input = table.find("input");
    input.keyup(function (ev) {
      // key-up is used instead of change
      const rawVal = this.value;
      if (!rawVal.match(/^[0-9a-fA-F]+$/) || parseInt(rawVal, 16) > 4092) {
        $(this).addClass("invalid");
      } else {
        $(this).removeClass("invalid");
        const offset = parseInt(rawVal, 16) * 16;
        if (currentOffset !== offset) {
          _updateTable(table, memory, offset);
          currentOffset = offset;
        }
      }
    });

    input.keydown(function (ev) {
      let action = 0;
      if (ev.originalEvent.key === "ArrowDown") {
        action = -1;
      } else if (ev.originalEvent.key === "ArrowUp") {
        action = 1;
      }

      if (action) {
        const rawVal = this.value;
        const val = parseInt(rawVal, 16);
        if (
          rawVal.match(/^[0-9a-fA-F]+$/) &&
          val + action >= 0 &&
          val + action <= 4092
        ) {
          this.value = (val + action).toString(16).padStart(3, "0");
        }
        ev.preventDefault();
      }
    });

    input.blur(function () {
      const rawVal = this.value;
      if (rawVal.match(/^[0-9a-fA-F]+$/)) {
        const val = parseInt(rawVal, 16);
        this.value = val.toString(16).padStart(3, "0");
      }
    });
  });
});
