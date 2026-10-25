<div class="wrap prose">
    <h1><?= ($kind ?? '') === 'prayer' ? 'Prayer request received' : 'Message received' ?></h1>
    <p><?= ($kind ?? '') === 'prayer' ? 'Your prayer request was received. It is kept for pastors and staff.' : 'Your message was received.' ?></p>
    <p><a href="/">Return home</a></p>
</div>
