const fs = require('fs');
let html = fs.readFileSync('contacts.php', 'utf8');

const modals = [];
// Regex to match the modal-overlay and extract its attributes and inner modal-content
const regex = /<!-- Modal \d[A-Z]?:.*?-->\s*<div class="modal-overlay" id="([^"]+)" role="dialog" aria-modal="true" aria-labelledby="([^"]+)">\s*([\s\S]*?\n    <\/div>)\n<\/div>/g;

html = html.replace(regex, (match, id, ariaLabelledby, innerContent) => {
    // Replace the opening modal-content tag to include the id, role, aria-modal, aria-labelledby, and new class 'child-modal-panel'
    let modifiedInner = innerContent.replace(
        /<div class="modal-content([^"]*)">/,
        `<div class="modal-content$1 child-modal-panel" id="${id}" role="dialog" aria-modal="true" aria-labelledby="${ariaLabelledby}" style="display: none;">`
    );
    modals.push(`<!-- Original Modal ID: ${id} -->\n    ${modifiedInner}`);
    return '<!-- MODAL_REPLACED -->';
});

if (modals.length > 0) {
    const wrapper = `
<!-- Top-level Child Modal Layer -->
<div class="contact-child-modal-layer" id="contactChildModalLayer" style="display: none;">
    <div class="contact-child-modal-backdrop" id="contactChildModalBackdrop" onclick="window.contactsApp.closeModal()"></div>
    ${modals.join('\n    ')}
</div>
`;
    // Replace the first placeholder with the wrapper, and remove the rest
    html = html.replace('<!-- MODAL_REPLACED -->', wrapper);
    html = html.replace(/<!-- MODAL_REPLACED -->\n?/g, '');
    
    fs.writeFileSync('contacts.php', html);
    console.log('Successfully updated contacts.php with grouped modals.');
} else {
    console.log('No modals found to replace.');
}
