import { useEffect, useState } from 'react';
import { MentorCourse } from '@/types/mentor';

const STORAGE_KEY = 'mentor_overview_state';

interface MentorStorageState {
    activeCategory: string;
    selectedCourseId: number | null;
}

/**
 * Custom hook to manage mentor overview state persistence
 * Saves and retrieves the active category and selected course from localStorage
 */
export function useMentorStorage(courses: MentorCourse[]) {
    const [activeCategory, setActiveCategory] = useState<string>('RTG');
    const [selectedCourse, setSelectedCourse] = useState<MentorCourse | null>(null);
    const [isInitialized, setIsInitialized] = useState(false);

    // Load state from localStorage on mount
    useEffect(() => {
        const loadState = () => {
            try {
                const savedState = localStorage.getItem(STORAGE_KEY);
                if (savedState) {
                    const parsedState: MentorStorageState = JSON.parse(savedState);
                    
                    // Restore active category
                    if (parsedState.activeCategory) {
                        setActiveCategory(parsedState.activeCategory);
                    }

                    // Restore selected course if it still exists
                    if (parsedState.selectedCourseId) {
                        const course = courses.find(c => c.id === parsedState.selectedCourseId);
                        if (course) {
                            setSelectedCourse(course);
                        }
                    }
                }
            } catch (error) {
                console.error('Error loading mentor storage state:', error);
                // Clear corrupted data
                localStorage.removeItem(STORAGE_KEY);
            }
            setIsInitialized(true);
        };

        loadState();
    }, [courses]);

    // Save state to localStorage whenever it changes
    useEffect(() => {
        // Don't save until we've initialized from storage
        if (!isInitialized) return;

        try {
            const state: MentorStorageState = {
                activeCategory,
                selectedCourseId: selectedCourse?.id || null,
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (error) {
            console.error('Error saving mentor storage state:', error);
        }
    }, [activeCategory, selectedCourse, isInitialized]);

    // Custom setter that updates both state and storage
    const updateActiveCategory = (category: string) => {
        setActiveCategory(category);
    };

    const updateSelectedCourse = (course: MentorCourse | null) => {
        setSelectedCourse(course);
    };

    // Clear storage (useful for logout or reset)
    const clearStorage = () => {
        try {
            localStorage.removeItem(STORAGE_KEY);
            setActiveCategory('RTG');
            setSelectedCourse(null);
        } catch (error) {
            console.error('Error clearing mentor storage:', error);
        }
    };

    return {
        activeCategory,
        selectedCourse,
        setActiveCategory: updateActiveCategory,
        setSelectedCourse: updateSelectedCourse,
        clearStorage,
        isInitialized,
    };
}