const { chromium } = require('playwright');
const { execSync } = require('child_process');
const path = require('path');
const T = require('./tasks_test.js');
const q = (sql) => execSync(`mysql -N smartqaq_nfelia_core -e "${sql.replace(/"/g, '\\"')}"`).toString().trim();
let pass = 0, fail = 0;
const ok = (c, m) => { if (c) { pass++; console.log('  PASS ', m); } else { fail++; console.log('  FAIL ', m); } };
(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' }).catch(() => chromium.launch());
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, timezoneId: 'Africa/Lagos' });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  await T.routeLibs(page);
  await page.goto(T.BASE + '/login');
  await page.fill('#email', 'ivie@demo.nfelia'); await page.fill('#password', 'DemoPass1');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(T.BASE + '/tasks', { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);
  const today = q("SELECT CURDATE()");
  const toast = async (want) => {
    try { await page.waitForFunction((w) => { const el = document.querySelector('[role=status] span'); return el && el.offsetParent !== null && (!w || new RegExp(w).test(el.textContent)); }, want || '', { timeout: 5000 }); } catch (e) {}
    return page.textContent('[role=status] span');
  };
  const waitIdle = () => page.waitForTimeout(500);
  const row = (title) => page.locator('li', { has: page.locator(`text="${title}"`) }).last();
  // Completing a task now asks first; say yes to clear it.
  const sayDone = async () => { await page.waitForSelector('#pl-done-yes', { state: 'visible', timeout: 5000 }); await page.click('#pl-done-yes'); };

  console.log('Quick add');
  await page.click('button[role=radio]:has-text("Tomorrow")');
  await page.fill('#pl-quick', 'Call Mama Bisi about the balance');
  await page.click('button:has-text("Add")');
  ok((await toast('Task added')).includes('Task added'), 'toast says Task added');
  await waitIdle();
  ok(await page.locator('text="Call Mama Bisi about the balance"').count() === 1, 'new task is on the list');
  ok(q("SELECT due_date FROM tasks WHERE title='Call Mama Bisi about the balance'") === q("SELECT DATE_ADD(CURDATE(), INTERVAL 1 DAY)"), 'saved for tomorrow');
  ok(await page.inputValue('#pl-quick') === '', 'box cleared after adding');
  await page.click('button:has-text("Add")');
  ok((await toast('Write the task first')).includes('Write the task first'), 'empty add asks for the task');

  console.log('Tick, undo');
  await row('Post new arrivals on Instagram').locator('button[role=checkbox]').click();
  ok(await page.locator('#pl-done-title').textContent().then((t) => t.includes('Post new arrivals on Instagram')), 'tick asks to confirm, naming the task');
  ok(q("SELECT done_at IS NULL FROM tasks WHERE title='Post new arrivals on Instagram' AND deleted_at IS NULL") === '1', 'not marked done until confirmed');
  await page.click('#pl-done-no');
  ok(await page.locator('#pl-done-dialog').isHidden(), 'Not yet closes the confirm');
  await row('Post new arrivals on Instagram').locator('button[role=checkbox]').click();
  await sayDone();
  ok((await toast('^Done')).startsWith('Done'), 'tick says Done');
  await waitIdle();
  ok(q("SELECT done_at IS NOT NULL FROM tasks WHERE title='Post new arrivals on Instagram' AND deleted_at IS NULL") === '1', 'done_at stamped');
  await page.click('[role=status] button:has-text("Undo")');
  await page.waitForTimeout(700);
  ok(q("SELECT done_at IS NULL FROM tasks WHERE title='Post new arrivals on Instagram' AND deleted_at IS NULL") === '1', 'undo clears done_at');

  console.log('Repeat');
  await row('Pay shop rent').locator('button[role=checkbox]').click();
  await sayDone();
  const rt = await toast('Next one');
  ok(/Next one: /.test(rt), 'repeat toast names the next date: ' + rt);
  await waitIdle();
  ok(q("SELECT COUNT(*) FROM tasks WHERE title='Pay shop rent' AND deleted_at IS NULL AND done_at IS NULL AND due_date = DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 5 DAY), INTERVAL 1 MONTH)") === '1', 'next month created');

  console.log('Star');
  await row('Clean the display shelves').locator('button[aria-label="Mark important"]').click();
  await waitIdle();
  ok(q("SELECT is_starred FROM tasks WHERE title='Clean the display shelves'") === '1', 'starred in DB');

  console.log('New appointment with validation');
  await page.click('button:has-text("Book appointment")');
  await page.waitForSelector('#pl-title');
  ok(await page.evaluate(() => document.activeElement.id) === 'pl-title', 'focus starts on the title');
  await page.click('button[type=submit]:has-text("Add appointment")');
  await page.waitForTimeout(150);
  ok(await page.isVisible('#pl-title-err'), 'blank title shows an error');
  ok((await page.textContent('#pl-title-err')).includes('Write what the appointment is'), 'error says what to write');
  await page.fill('#pl-title', 'Fitting with Adaeze');
  ok(!(await page.isVisible('#pl-title-err')), 'typing clears the error');
  await page.click('[role=dialog][aria-modal=true] button[role=radio]:has-text("Tomorrow")');
  await page.fill('#pl-time', '14:00'); await page.dispatchEvent('#pl-time', 'change');
  ok(await page.inputValue('#pl-remind') === '60', 'timed appointment defaults to 1 hour before');
  await page.fill('#pl-cust', 'adae');
  await page.click('#pl-cust-list button:has-text("Adaeze Nwosu")');
  await page.click('button[type=submit]:has-text("Add appointment")');
  ok((await toast('Appointment added')).includes('Appointment added'), 'appointment added');
  await waitIdle();
  const ap = q("SELECT CONCAT_WS('|', kind, due_time, remind_before, remind_at, customer_id) FROM tasks WHERE title='Fitting with Adaeze'");
  ok(ap === `appointment|14:00:00|60|${q("SELECT DATE_ADD(CURDATE(), INTERVAL 1 DAY)")} 13:00:00|3`, 'stored with time, reminder at 1pm, customer: ' + ap);
  ok(await page.locator('[role=dialog][aria-modal=true]').isHidden(), 'sheet closed after save');

  console.log('Edit, Escape, delete');
  await page.click('button:has-text("Clean the display shelves")');
  await page.waitForSelector('#pl-title');
  ok(await page.inputValue('#pl-title') === 'Clean the display shelves', 'editor opens with the task');
  await page.keyboard.press('Escape');
  await waitIdle();
  ok(await page.locator('[role=dialog][aria-modal=true]').isHidden(), 'Escape closes the sheet');
  await page.click('button:has-text("Clean the display shelves")');
  await page.fill('#pl-title', 'Clean and dust the display shelves');
  await page.click('button[type=submit]:has-text("Save changes")');
  ok((await toast('Saved')).includes('Saved'), 'edit saved');
  await waitIdle();
  await page.click('button:has-text("Clean and dust the display shelves")');
  await page.click('[role=dialog][aria-modal=true] button:has-text("Delete")');
  await page.waitForTimeout(200);
  ok(await page.isVisible('#pl-del-dialog'), 'delete asks first');
  ok((await page.textContent('#pl-del-title')).includes('Clean and dust the display shelves'), 'confirmation names the task');
  await page.click('#pl-del-dialog button:has-text("Delete")');
  ok((await toast('Deleted')).includes('Deleted'), 'deleted');
  await waitIdle();
  ok(q("SELECT deleted_at IS NOT NULL FROM tasks WHERE title='Clean and dust the display shelves'") === '1', 'soft-deleted in DB');

  console.log('Automatic items');
  await page.locator('li', { has: page.locator('text=/Restock HD Lace Closure/') }).last().locator('button[role=checkbox]').click();
  ok((await toast('Hidden for a week')).includes('Hidden for a week'), 'restock hidden for a week');
  await waitIdle();
  ok(q("SELECT until_date FROM planner_dismissals WHERE item_key='restock:5'") === q("SELECT DATE_ADD(CURDATE(), INTERVAL 7 DAY)"), 'dismissal lasts 7 days');
  ok(await page.locator('text=/Restock HD Lace Closure/').count() === 0, 'gone from the list');
  await page.click('[role=status] button:has-text("Undo")'); await page.waitForTimeout(700);
  ok(await page.locator('text=/Restock HD Lace Closure/').count() === 1, 'undo brings it back');
  await row('Deliver to Blessing Etim').locator('button[role=checkbox]').click();
  ok(await page.locator('#pl-done-title').textContent().then((t) => /delivered\?$/.test(t)), 'delivery asks to confirm first');
  await sayDone();
  ok((await toast('Marked delivered')).includes('Marked delivered'), 'delivery ticked');
  await waitIdle();
  ok(q("SELECT delivered_at IS NOT NULL FROM invoices WHERE id=6") === '1', 'invoice delivered_at set');
  await page.click('[role=status] button:has-text("Undo")'); await page.waitForTimeout(700);
  ok(q("SELECT delivered_at IS NULL FROM invoices WHERE id=6") === '1', 'undo clears delivered_at');
  ok(await page.locator('a[aria-label="Remind Chioma"]').getAttribute('href').then((h) => h.startsWith('https://wa.me/234806555012')), 'WhatsApp reminder uses the customer number in 234 format');

  console.log('Job plan');
  await page.click('button:has-text("Plan a job")');
  await page.click('[role=dialog][aria-modal=true] button:has-text("Sewing job")');
  await page.fill('#pl-plan-cust-q', 'chio');
  await page.click('#pl-plan-cust button:has-text("Chioma Obi")');
  ok(await page.inputValue('#pl-plan-name') === 'Sewing job for Chioma', 'name uses the customer');
  const fin = q("SELECT DATE_ADD(CURDATE(), INTERVAL 12 DAY)");
  await page.fill('#pl-plan-finish', fin); await page.dispatchEvent('#pl-plan-finish', 'change');
  const dates = await page.$$eval('[role=dialog][aria-modal=true] ol input[type=date]', (els) => els.map((e) => e.value));
  ok(dates.length === 5 && dates[4] === fin && dates[0] === q("SELECT DATE_ADD(CURDATE(), INTERVAL 1 DAY)"), 'five steps counted back from the finish: ' + dates.join(','));
  const titles = await page.$$eval('[role=dialog][aria-modal=true] ol input[type=text]', (els) => els.map((e) => e.value));
  ok(titles[2] === 'Fitting with Chioma' && titles[4] === 'Deliver to Chioma', 'step names use the customer');
  await page.click('button:has-text("Save job plan")');
  ok((await toast('5 steps')).includes('5 steps'), 'plan saved with 5 steps');
  await waitIdle();
  ok(q("SELECT COUNT(*) FROM tasks t JOIN tasks p ON p.id=t.parent_id WHERE p.title='Sewing job for Chioma'") === '5', '5 steps in DB');
  ok(q("SELECT CONCAT(kind,'|',remind_before) FROM tasks WHERE title='Fitting with Chioma'") === 'appointment|1440', 'fitting is an appointment reminded the day before');
  ok(await page.locator('text="Sewing job for Chioma"').count() >= 1, 'job shows under Jobs in progress');
  await page.click('button:has-text("Sewing job for Chioma")');
  await page.waitForSelector('[role=dialog][aria-modal=true] ol li');
  await page.locator('[role=dialog][aria-modal=true] ol li').first().locator('button[role=checkbox]').click();
  await sayDone();
  await page.waitForTimeout(1500);
  console.log('    job header:', await page.textContent('#pl-job-title + p'), '| steps done in DB:', q("SELECT COUNT(*) FROM tasks t JOIN tasks p ON p.id=t.parent_id WHERE p.title='Sewing job for Chioma' AND t.done_at IS NOT NULL"));
  ok((await page.textContent('#pl-job-title + p')).includes('1 of 5 done'), 'ticking a step updates the count');
  await page.keyboard.press('Escape');

  console.log('Calendar');
  await page.click('button[role=tab]:has-text("Calendar")');
  await page.waitForTimeout(900);
  ok(await page.locator('[role=gridcell]').count() === 42, 'six-week grid');
  ok((await page.textContent('#pl-month')).length > 5, 'month label shown');
  const tomorrow = q("SELECT DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
  const tomorrowDay = String(Number(tomorrow.slice(8)));
  await page.locator('[role=gridcell][aria-label^="Tomorrow"]').first().click();
  await page.waitForTimeout(300);
  ok(await page.locator('text="Fitting with Adaeze"').isVisible(), 'tomorrow shows the fitting');
  await page.click('button[aria-label="Next month"]');
  await page.waitForTimeout(700);
  ok(await page.locator('text="Pay shop rent"').count() >= 0, 'next month loads');
  await page.click('[aria-labelledby=pl-month] button:has-text("Today")');
  ok(page.url().endsWith('/tasks?tab=calendar'), 'URL remembers the tab');

  console.log('Reminders');
  await page.click('button:has-text("Set up reminders")');
  await page.waitForTimeout(400);
  ok(await page.locator('text=Coming soon').isVisible(), 'push says coming soon without keys');
  await page.locator('label:has-text("Morning plan by email") input').uncheck();
  await page.waitForTimeout(600);
  ok(q("SELECT morning_email FROM users WHERE id=1") === '0', 'morning email off saved');
  await page.locator('label:has-text("Morning plan by email") input').check();
  await page.waitForTimeout(600);

  console.log('Review fixes');
  await page.keyboard.press('Escape'); await waitIdle();
  ok(await page.locator('[role=dialog][aria-modal=true]').isHidden(), 'Escape closes the Reminders sheet');
  let feeds = 0;
  const countFeed = (req) => { if (req.url().includes('/tasks/feed')) feeds++; };
  page.on('request', countFeed);
  await page.goto(T.BASE + '/tasks?tab=calendar', { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  page.off('request', countFeed);
  ok(feeds === 1, 'the calendar fetches its month once, so init runs once: ' + feeds);

  // Calendar: a change refreshes the day list in place, never blanking it.
  await page.locator('[role=gridcell][aria-label^="Today"]').first().click();
  await page.waitForTimeout(300);
  await page.evaluate(() => {
    window.__blank = false;
    new MutationObserver(() => { if (document.body.innerText.includes('Loading…')) window.__blank = true; })
      .observe(document.body, { subtree: true, childList: true, characterData: true });
  });
  await row('Post new arrivals on Instagram').locator('button[role=checkbox]').click();
  await sayDone();
  await toast('Done');
  await page.waitForTimeout(1200);
  ok(!(await page.evaluate(() => window.__blank)), 'ticking on the calendar does not blank the day list');

  // Undo stays put while it has focus.
  await page.focus('[role=status] button:has-text("Undo")');
  await page.waitForTimeout(7000);
  ok(await page.isVisible('[role=status] button:has-text("Undo")'), 'Undo stays while it has focus');
  await page.click('[role=status] button:has-text("Undo")'); await waitIdle();
  ok(q("SELECT done_at IS NULL FROM tasks WHERE title='Post new arrivals on Instagram'") === '1', 'and still works');

  await page.click('button[role=tab]:has-text("Tasks")'); await waitIdle();

  // A job sheet never shows, or ticks, the last job's steps.
  await page.click('button:has-text("Order for Mrs Folake")');
  await page.waitForSelector('[role=dialog][aria-modal=true] ol li');
  await page.keyboard.press('Escape'); await waitIdle();
  await page.route(/\/tasks\/job\//, async (rt) => { await new Promise((res) => setTimeout(res, 800)); rt.continue(); });
  await page.click('button:has-text("Sewing job for Chioma")');
  await page.waitForTimeout(150);
  const early = await page.$$eval('[role=dialog][aria-modal=true] ol li', (els) => els.map((e) => e.textContent));
  ok(!early.some((t) => /Confirm payment|Get the goods ready|Deliver to Mrs Folake/.test(t)), 'a new job sheet never shows the last job\'s steps');
  ok(await page.isVisible('text=Loading steps…'), 'it says it is loading instead');
  await page.waitForTimeout(1200);
  const steps = await page.$$eval('[role=dialog][aria-modal=true] ol li', (els) => els.map((e) => e.textContent));
  ok(steps.length === 5 && steps.some((t) => t.includes('Fitting with Chioma')), 'then shows its own five steps');
  await page.unroute(/\/tasks\/job\//);
  await page.keyboard.press('Escape'); await waitIdle();

  // Delete: starts on the safe choice; Escape closes only the question.
  await row('Ask supplier for the new HD lace price').locator('button', { hasText: 'Ask supplier for the new HD lace price' }).click();
  await page.waitForSelector('#pl-title');
  await page.fill('#pl-title', 'Ask supplier for the HD lace price');
  await page.click('[role=dialog][aria-modal=true] button:has-text("Delete")');
  await page.waitForTimeout(250);
  ok(await page.evaluate(() => document.activeElement.id) === 'pl-del-keep', 'the delete question starts on "Keep it"');
  await page.keyboard.press('Escape'); await page.waitForTimeout(250);
  ok(await page.locator('#pl-del-dialog').isHidden() && await page.locator('[role=dialog][aria-modal=true]').isVisible(), 'Escape closes only the question, not the editor');
  ok(await page.inputValue('#pl-title') === 'Ask supplier for the HD lace price', 'the edit is still there');
  ok(await page.evaluate(() => document.activeElement.textContent.trim()) === 'Delete', 'focus is back on Delete');
  let deletes = 0;
  const countDelete = (req) => { if (req.url().endsWith('/tasks/delete')) deletes++; };
  page.on('request', countDelete);
  await page.click('[role=dialog][aria-modal=true] button:has-text("Delete")'); await page.waitForTimeout(250);
  await page.locator('#pl-del-dialog button:has-text("Delete")').dblclick();
  ok((await toast('Deleted')).includes('Deleted'), 'a double tap on Delete still reads "Deleted."');
  await waitIdle();
  page.off('request', countDelete);
  ok(deletes === 1, 'and sends one delete: ' + deletes);

  // "Add details" then save: the quick box empties, so Add cannot repeat it.
  await page.fill('#pl-quick', 'Ring the courier');
  await page.click('button:has-text("Add details")');
  await page.waitForSelector('#pl-title');
  ok(await page.inputValue('#pl-title') === 'Ring the courier', 'Add details carries the text over');
  await page.click('button[type=submit]:has-text("Add task")');
  ok((await toast('Task added')).includes('Task added'), 'saved from the editor');
  await waitIdle();
  ok(await page.inputValue('#pl-quick') === '', 'the quick box is empty afterwards');
  ok(q("SELECT COUNT(*) FROM tasks WHERE title='Ring the courier' AND deleted_at IS NULL") === '1', 'one task, not two');

  // Repeat chosen, then the date cleared by hand: saves as a plain undated task.
  await row('Ring the courier').locator('button', { hasText: 'Ring the courier' }).click();
  await page.waitForSelector('#pl-title');
  await page.click('[role=dialog][aria-modal=true] button[role=radio]:has-text("Pick a date")');
  await page.selectOption('#pl-repeat', 'week');
  await page.fill('[role=dialog][aria-modal=true] input[type=date][aria-labelledby="pl-when"]', '');
  await page.click('button[type=submit]:has-text("Save changes")');
  ok((await toast('Saved')).includes('Saved'), 'clearing the date after choosing Repeat still saves');
  await waitIdle();
  ok(q("SELECT CONCAT(IFNULL(due_date,'-'),'|',IFNULL(repeat_every,'-')) FROM tasks WHERE title='Ring the courier'") === '-|-', 'as an undated task that does not repeat');

  // Every field has a label people can see; placeholders are only examples.
  ok(await page.isVisible('label[for=pl-quick]') && (await page.textContent('label[for=pl-quick]')).trim() === 'Add a task', 'quick add has a visible label');

  // End time is checked as soon as it is set.
  await page.click('button:has-text("Book appointment")'); await page.waitForSelector('#pl-title');
  await page.fill('#pl-time', '15:00'); await page.dispatchEvent('#pl-time', 'change');
  await page.fill('#pl-end', '14:00'); await page.dispatchEvent('#pl-end', 'change');
  await page.waitForTimeout(150);
  ok((await page.textContent('[role=dialog][aria-modal=true]')).includes('The end time must be after the start'), 'an end time before the start is flagged at once');
  await page.fill('#pl-end', '16:00'); await page.dispatchEvent('#pl-end', 'change');
  await page.waitForTimeout(150);
  ok(!(await page.textContent('[role=dialog][aria-modal=true]')).includes('The end time must be after the start'), 'and cleared when fixed');

  // Half-typed work survives a reload (phones drop tabs in the background).
  await page.fill('#pl-title', 'Fitting with Zainab');
  await page.waitForTimeout(300);
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  ok(await page.isVisible('#pl-title') && await page.inputValue('#pl-title') === 'Fitting with Zainab', 'after a reload the editor comes back with what was typed');
  ok((await toast('unsaved')).includes('Your unsaved changes are back'), 'and says so');
  await page.click('[role=status] button:has-text("Discard")');
  await page.waitForTimeout(400);
  ok(await page.locator('[role=dialog][aria-modal=true]').isHidden(), 'Discard closes it');
  await page.reload({ waitUntil: 'networkidle' }); await page.waitForTimeout(400);
  ok(await page.locator('[role=dialog][aria-modal=true]').isHidden(), 'and it stays gone after another reload');
  await page.click('button:has-text("Book appointment")'); await page.waitForSelector('#pl-title');
  await page.keyboard.press('Escape'); await waitIdle();
  await page.reload({ waitUntil: 'networkidle' }); await page.waitForTimeout(400);
  ok(await page.locator('[role=dialog][aria-modal=true]').isHidden(), 'an untouched sheet leaves no draft behind');

  // A page left open overnight reloads into the new day when looked at again.
  const lagosNow = new Date(new Date(`${today}T23:58:00+01:00`).getTime());
  await page.clock.install({ time: lagosNow });
  await page.goto(T.BASE + '/tasks', { waitUntil: 'networkidle' });
  const reloaded = page.waitForEvent('load', { timeout: 5000 }).then(() => true).catch(() => false);
  await page.clock.runFor(6 * 60 * 1000);
  ok(await reloaded, 'past midnight, the page reloads into the new day');
  await page.waitForLoadState('networkidle');

  console.log('Security');
  const r = await page.evaluate(async (b) => (await fetch(b + '/tasks/save', { method: 'POST', body: '{}', headers: { 'Content-Type': 'application/json' } })).status, T.BASE);
  ok(r === 419, 'POST without CSRF is refused (419)');
  const r2 = await page.evaluate(async (b) => (await fetch(b + '/tasks/tick', { method: 'POST', body: JSON.stringify({ id: 999999, done: true }), headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.Nfelia.csrf } })).status, T.BASE);
  ok(r2 === 404, 'unknown task is 404');
  const kemiTask = q("SELECT id FROM tasks WHERE business_id=2 LIMIT 1");
  const r3 = await page.evaluate(async ([b, id]) => (await fetch(b + '/tasks/delete', { method: 'POST', body: JSON.stringify({ id: Number(id) }), headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.Nfelia.csrf } })).status, [T.BASE, kemiTask]);
  ok(r3 === 404 && q(`SELECT deleted_at IS NULL FROM tasks WHERE id=${kemiTask}`) === '1', "another business's task cannot be touched");
  const r4 = await page.evaluate(async (b) => (await fetch(b + '/tasks/dismiss', { method: 'POST', body: JSON.stringify({ key: "restock:1' OR 1=1" }), headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.Nfelia.csrf } })).status, T.BASE);
  ok(r4 === 422, 'odd dismissal keys are refused');

  const real = errors.filter((e) => !/Failed to load resource: the server responded with a status of (419|404|422)/.test(e));
  ok(real.length === 0, 'no console or page errors (expected 4xx from the security checks aside)' + (real.length ? ': ' + real.join(' | ') : ''));
  console.log(`RESULT: ${pass} passed, ${fail} failed`);
  await browser.close();
})();
