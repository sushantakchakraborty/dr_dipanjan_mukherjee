// Vercel serverless function - handles the contact form for Dr. Dipanjan Mukherjee
const nodemailer = require('nodemailer');

// ===== Mail settings =====
// Sender credentials (kept unchanged)
const SMTP_USER = 'info.skcinfotech@gmail.com';
const SMTP_PASS = 'ckkbmyafolcnfkux';

// Enquiries are received here
const MAIL_TO = 'doc.dipanjan@rediffmail.com';
// ==========================

function esc(s) {
  return String(s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function respond(req, res, ok, message, code) {
  const accept = req.headers['accept'] || '';
  const isAjax =
    (req.headers['x-requested-with'] || '').toLowerCase() === 'xmlhttprequest' ||
    (req.body && req.body.ajax === '1') ||
    accept.includes('application/json');

  if (isAjax) {
    return res.status(code).json({ status: ok ? 'success' : 'error', message });
  }
  res.setHeader('Location', `/contact.html?status=${ok ? 'success' : 'error'}`);
  return res.status(303).end();
}

module.exports = async (req, res) => {
  if (req.method !== 'POST') {
    return respond(req, res, false, 'Invalid request method. Only POST is accepted.', 405);
  }

  let body = req.body || {};
  if (typeof body === 'string') body = Object.fromEntries(new URLSearchParams(body));

  const name = String(body.name || '').trim();
  const email = String(body.email || '').trim();
  const subject = String(body.subject || '').trim().replace(/[\r\n]+/g, ' ');
  const message = String(body.message || '').trim();

  if (!name) return respond(req, res, false, 'Please enter your name.', 400);
  if (!email) return respond(req, res, false, 'Please enter your email address.', 400);
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    return respond(req, res, false, 'Please enter a valid email address.', 400);
  }
  if (!subject) return respond(req, res, false, 'Please enter a subject.', 400);
  if (!message) return respond(req, res, false, 'Please enter your message.', 400);
  if (/[\r\n]/.test(name) || /[\r\n]/.test(email)) {
    return respond(req, res, false, 'Invalid characters detected in form submission.', 400);
  }

  const row = (label, val) =>
    `<tr><td style="padding:8px 0;width:150px;color:#666;font-weight:bold;">${label}</td><td style="padding:8px 0;color:#222;">${esc(val)}</td></tr>`;

  try {
    const transporter = nodemailer.createTransport({
      host: 'smtp.gmail.com',
      port: 587,
      secure: false,
      auth: { user: SMTP_USER, pass: SMTP_PASS },
    });

    await transporter.sendMail({
      from: { name: 'Dr. Dipanjan Mukherjee Website', address: SMTP_USER },
      to: MAIL_TO,
      replyTo: { name, address: email },
      subject: 'New Website Enquiry - Dr. Dipanjan Mukherjee',
      html: `
        <div style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;border:1px solid #e0e0e0;border-radius:8px;padding:24px;background:#fff;">
          <div style="border-bottom:2px solid #009dff;padding-bottom:12px;margin-bottom:20px;">
            <h2 style="color:#1f1f1f;margin:0 0 4px 0;font-size:20px;">Dr. Dipanjan Mukherjee</h2>
            <span style="font-size:13px;color:#888;text-transform:uppercase;letter-spacing:1px;">New Website Contact Form Submission</span>
          </div>
          <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
            ${row('Name:', name)}${row('Email:', email)}${row('Subject:', subject)}
          </table>
          <div style="background:#f7f9fb;border-left:4px solid #009dff;padding:16px;border-radius:4px;margin-bottom:20px;">
            <strong style="display:block;color:#1f1f1f;margin-bottom:8px;">Message:</strong>
            <p style="margin:0;color:#333;">${esc(message).replace(/\n/g, '<br>')}</p>
          </div>
          <p style="font-size:12px;color:#999;margin:0;">This enquiry was submitted from the Dr. Dipanjan Mukherjee website contact form.</p>
        </div>`,
      text:
        `New Website Enquiry - Dr. Dipanjan Mukherjee\n\nName: ${name}\nEmail: ${email}\n` +
        `Subject: ${subject}\n\nMessage:\n${message}\n`,
    });

    return respond(req, res, true, 'Your message has been sent successfully.', 200);
  } catch (err) {
    console.error('Mail error:', err.message);
    return respond(req, res, false, 'Unable to send your message. Please try again later.', 500);
  }
};
