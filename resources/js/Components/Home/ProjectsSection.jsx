import { useTranslation } from 'react-i18next';
import Container from '@/Components/Common/Container';
import SectionHeader from '@/Components/Common/SectionHeader';
import ProjectCard from '@/Components/Projects/ProjectCard';

export default function ProjectsSection({ projects }) {
    const { t } = useTranslation();
    if (!projects.length) return null;
    return <section className="corporate-section group-projects" id="projects" aria-labelledby="projects-title">
        <Container><SectionHeader id="projects-title" eyebrow={t('corporateHome.projectsEyebrow')} title={t('corporateHome.projectsTitle')} />
            <div className="corporate-grid corporate-grid--three">{projects.map((project, index) => <ProjectCard key={project.id} project={project} index={index} />)}</div>
        </Container>
    </section>;
}
