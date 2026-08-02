import { Head } from '@inertiajs/react';
import { lazy, Suspense } from 'react';
import Navbar from '../Components/Navbar';
import HeroSection from '../Sections/HeroSection';

const ServicesSection = lazy(() => import('../Sections/ServicesSection'));
const AboutSection = lazy(() => import('../Sections/AboutSection'));
const BenefitsSection = lazy(() => import('../Sections/BenefitsSection'));
const ProcessSection = lazy(() => import('../Sections/ProcessSection'));
const PricingSection = lazy(() => import('../Sections/PricingSection'));
const TestimonialsSection = lazy(() => import('../Sections/TestimonialsSection'));
const CtaSection = lazy(() => import('../Sections/CtaSection'));
const ContactSection = lazy(() => import('../Sections/ContactSection'));
const FooterSection = lazy(() => import('../Sections/FooterSection'));

function SectionFallback() {
    return <div className="min-h-[40vh] bg-paper" aria-hidden />;
}

export default function Landing({ sections, settings }) {
    const whatsapp = (settings.whatsapp || '').replace(/\D/g, '');
    const whatsappUrl = `https://wa.me/${whatsapp}`;

    return (
        <>
            <Head>
                <title>{settings.seo_title || settings.company_name}</title>
                <meta
                    head-key="description"
                    name="description"
                    content={settings.seo_description || settings.tagline || ''}
                />
            </Head>

            <div className="bg-paper">
                <Navbar settings={settings} whatsappUrl={whatsappUrl} />
                <HeroSection
                    section={sections.hero}
                    settings={settings}
                    whatsappUrl={whatsappUrl}
                />

                <Suspense fallback={<SectionFallback />}>
                    <ServicesSection section={sections.services} />
                    <AboutSection section={sections.about} />
                    <BenefitsSection section={sections.benefits} />
                    <ProcessSection section={sections.process} />
                    <PricingSection
                        section={sections.pricing}
                        whatsappUrl={whatsappUrl}
                        settings={settings}
                    />
                    <TestimonialsSection section={sections.testimonials} />
                    <CtaSection section={sections.cta} whatsappUrl={whatsappUrl} />
                    <ContactSection
                        section={sections.contact}
                        settings={settings}
                        whatsappUrl={whatsappUrl}
                    />
                    <FooterSection section={sections.footer} settings={settings} />
                </Suspense>
            </div>
        </>
    );
}
