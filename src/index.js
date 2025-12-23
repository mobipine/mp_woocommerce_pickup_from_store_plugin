import { decodeEntities } from '@wordpress/html-entities';

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;

const settings = getSetting('pickup_from_store_data', {});

const label = decodeEntities(settings.title);

const Content = () => {
    return (
        <div>
            <p>{decodeEntities(settings.description || '')}</p>
        </div>
    );
};

const Label = () => {
    return (
        <span style={{ width: '100%', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            {label}
        </span>
    );
};

registerPaymentMethod({
    name: 'pickup_from_store',
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports || ['products'],
    },
});

