      <label class="<?= $label ?>" for="cronMode">Schedule</label>
      <select id="cronMode" class="<?= $controlSelect ?>">
        <option value="minute" selected>Every minute</option>
        <option value="hourly">Hourly</option>
        <option value="daily">Daily</option>
        <option value="weekly">Weekly</option>
        <option value="monthly">Monthly</option>
        <option value="custom">Custom</option>
      </select>
      <div id="cronHourly" class="mt-4" hidden>
        <label class="<?= $label ?>" for="cronHourlyMinute">Minute</label>
        <input id="cronHourlyMinute" class="<?= $field ?>" type="number" min="0" max="59" value="0">
      </div>
      <div id="cronDaily" class="mt-4 grid gap-3 sm:grid-cols-2" hidden>
        <div>
          <label class="<?= $label ?>" for="cronDailyHour">Hour</label>
          <input id="cronDailyHour" class="<?= $field ?>" type="number" min="0" max="23" value="0">
        </div>
        <div>
          <label class="<?= $label ?>" for="cronDailyMinute">Minute</label>
          <input id="cronDailyMinute" class="<?= $field ?>" type="number" min="0" max="59" value="0">
        </div>
      </div>
      <div id="cronWeekly" class="mt-4 grid gap-3 sm:grid-cols-2" hidden>
        <div class="sm:col-span-2">
          <label class="<?= $label ?>" for="cronWeeklyDay">Weekday</label>
          <select id="cronWeeklyDay" class="<?= $controlSelect ?>">
            <option value="0" selected>Sunday</option>
            <option value="1">Monday</option>
            <option value="2">Tuesday</option>
            <option value="3">Wednesday</option>
            <option value="4">Thursday</option>
            <option value="5">Friday</option>
            <option value="6">Saturday</option>
          </select>
        </div>
        <div>
          <label class="<?= $label ?>" for="cronWeeklyHour">Hour</label>
          <input id="cronWeeklyHour" class="<?= $field ?>" type="number" min="0" max="23" value="0">
        </div>
        <div>
          <label class="<?= $label ?>" for="cronWeeklyMinute">Minute</label>
          <input id="cronWeeklyMinute" class="<?= $field ?>" type="number" min="0" max="59" value="0">
        </div>
      </div>
      <div id="cronMonthly" class="mt-4 grid gap-3 sm:grid-cols-2" hidden>
        <div class="sm:col-span-2">
          <label class="<?= $label ?>" for="cronMonthlyDay">Day of month</label>
          <input id="cronMonthlyDay" class="<?= $field ?>" type="number" min="1" max="31" value="1">
        </div>
        <div>
          <label class="<?= $label ?>" for="cronMonthlyHour">Hour</label>
          <input id="cronMonthlyHour" class="<?= $field ?>" type="number" min="0" max="23" value="0">
        </div>
        <div>
          <label class="<?= $label ?>" for="cronMonthlyMinute">Minute</label>
          <input id="cronMonthlyMinute" class="<?= $field ?>" type="number" min="0" max="59" value="0">
        </div>
      </div>
      <div id="cronCustom" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" hidden>
        <div>
          <label class="<?= $label ?>" for="cronCustomMinute">Minute</label>
          <input id="cronCustomMinute" class="<?= $field ?>" value="*" spellcheck="false" autocomplete="off">
        </div>
        <div>
          <label class="<?= $label ?>" for="cronCustomHour">Hour</label>
          <input id="cronCustomHour" class="<?= $field ?>" value="*" spellcheck="false" autocomplete="off">
        </div>
        <div>
          <label class="<?= $label ?>" for="cronCustomDom">Day of month</label>
          <input id="cronCustomDom" class="<?= $field ?>" value="*" spellcheck="false" autocomplete="off">
        </div>
        <div>
          <label class="<?= $label ?>" for="cronCustomMonth">Month</label>
          <input id="cronCustomMonth" class="<?= $field ?>" value="*" spellcheck="false" autocomplete="off">
        </div>
        <div>
          <label class="<?= $label ?>" for="cronCustomDow">Day of week</label>
          <input id="cronCustomDow" class="<?= $field ?>" value="*" spellcheck="false" autocomplete="off">
        </div>
      </div>
      <div class="<?= $stat ?> mt-4">
        <span class="text-xs text-muted">Expression</span>
        <strong id="cronOutput" class="mt-1 block break-all font-mono text-xl font-bold tracking-tight sm:text-2xl">* * * * *</strong>
        <small id="cronHuman" class="mt-1 block text-xs text-muted">Every minute</small>
      </div>
      <button class="<?= $btnPrimary ?> mt-4" id="copy" type="button">Copy</button>
      <p class="<?= $hint ?>">Choose a schedule. The expression updates as you change fields. Stays in your browser.</p>
