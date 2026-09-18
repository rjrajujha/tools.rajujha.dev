      <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <strong class="block text-sm font-bold text-ink">Markdown Preview</strong>
          <span class="text-xs text-muted" id="mdStats">0 characters · processed locally</span>
        </div>
        <div class="grid grid-cols-3 gap-2 sm:flex sm:flex-wrap">
          <button id="mdSample" type="button" class="<?= $btn ?>">Sample</button>
          <button id="mdClear" type="button" class="<?= $btn ?>">Clear</button>
          <button id="copy" type="button" class="<?= $btn ?>">Copy</button>
        </div>
      </div>
      <div class="grid min-h-[min(70vh,720px)] gap-3 lg:h-[min(70vh,720px)] lg:grid-cols-2">
        <div class="flex min-h-64 flex-col overflow-hidden rounded-2xl border border-line bg-panel lg:min-h-0">
          <div class="border-b border-line bg-white/70 px-4 py-3">
            <label class="text-sm font-semibold text-ink" for="input">Editor</label>
          </div>
          <textarea id="input" class="min-h-0 w-full flex-1 resize-none border-0 bg-[#fcfdfb] px-4 py-4 font-mono text-base leading-relaxed text-ink outline-none focus:ring-0 sm:text-sm" placeholder="# Hello&#10;&#10;Write **Markdown** here.&#10;&#10;- Fast&#10;- Local&#10;- Private" spellcheck="false"></textarea>
        </div>
        <div class="flex min-h-64 flex-col overflow-hidden rounded-2xl border border-line bg-white lg:min-h-0">
          <div class="border-b border-line bg-white/70 px-4 py-3">
            <label class="text-sm font-semibold text-ink">Preview</label>
          </div>
          <article id="preview" class="markdown-preview min-h-0 flex-1 overflow-auto px-4 py-4 sm:px-5 sm:py-5"></article>
        </div>
      </div>
