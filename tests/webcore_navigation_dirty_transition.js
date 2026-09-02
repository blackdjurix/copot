const fs = require('fs');
const vm = require('vm');

const source = fs.readFileSync('public/admin-assets/js/navigation-admin.js', 'utf8');
const request = source.match(/function requestTransition\(destination\) \{[\s\S]*?\n    \}/);
const guarded = source.match(/function guardedNavigation\(event, link\) \{[\s\S]*?\n    \}/);

if (!request || !guarded) throw new Error('Navigation dirty-transition helpers are unavailable.');
if (source.includes('cancel.click()')) throw new Error('Backdrop dismissal must not re-dispatch Cancel.');
if (!source.includes("[data-navigation-cancel]")) throw new Error('Cancel is not wired through the transition guard.');
if (!source.includes("var link = event.target.closest('a');")) throw new Error('Tree and Add item links are not wired through the transition guard.');
if (!source.includes("if (backdrop) backdrop.addEventListener('click', function () { requestTransition(")) throw new Error('Backdrop dismissal is not wired through the transition guard.');

let assertions = 4;
const run = (isDirty, confirmResult, destination = '/admin/navigation') => {
    const state = { confirms: 0, assigned: null, prevented: false };
    const context = {
        dirty: () => isDirty,
        window: {
            confirm: () => { state.confirms += 1; return confirmResult; },
            location: { assign: (url) => { state.assigned = url; } },
        },
        state,
    };
    vm.createContext(context);
    vm.runInContext(`${request[0]}\n${guarded[0]}\nstate.result = requestTransition(${JSON.stringify(destination)});`, context);
    return state;
};

let result = run(false, false);
if (result.confirms !== 0 || result.assigned !== '/admin/navigation' || result.result !== true) throw new Error('Pristine transition did not proceed directly.');
assertions += 1;

result = run(true, false);
if (result.confirms !== 1 || result.assigned !== null || result.result !== false) throw new Error('Rejected dirty transition was not preserved.');
assertions += 1;

result = run(true, true, '/admin/navigation?create=1');
if (result.confirms !== 1 || result.assigned !== '/admin/navigation?create=1' || result.result !== true) throw new Error('Confirmed dirty transition did not navigate exactly once.');
assertions += 1;

const guardedState = { confirms: 0, assigned: null, prevented: false };
const guardedContext = {
    dirty: () => true,
    window: {
        confirm: () => { guardedState.confirms += 1; return true; },
        location: { assign: (url) => { guardedState.assigned = url; } },
    },
    state: guardedState,
};
vm.createContext(guardedContext);
vm.runInContext(`${request[0]}\n${guarded[0]}\nguardedNavigation({ preventDefault: () => { state.prevented = true; } }, { href: '/admin/navigation?item=2' });`, guardedContext);
if (!guardedState.prevented || guardedState.confirms !== 1 || guardedState.assigned !== '/admin/navigation?item=2') throw new Error('Dirty tree/Add transition did not use the shared guard.');
assertions += 1;

console.log(`WU3 Navigation dirty transition passed (${assertions} assertions).`);
