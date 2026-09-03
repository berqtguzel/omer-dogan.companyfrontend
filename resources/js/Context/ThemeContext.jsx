import React, {
    createContext,
    useContext,
    useEffect,
    useMemo,
    useState,
} from "react";

const ThemeContext = createContext({
    theme: "light",
    setTheme: () => {},
    toggleTheme: () => {},
});

export const useTheme = () => useContext(ThemeContext);

export const ThemeProvider = ({ children, initial = "system" }) => {
    const [theme, setTheme] = useState("");

    useEffect(() => {
        let nextTheme = "light";
        const saved = localStorage.getItem("theme");

        if (saved === "light" || saved === "dark") {
            nextTheme = saved;
        } else if (initial === "dark") {
            nextTheme = "dark";
        } else if (
            initial === "system" &&
            window.matchMedia("(prefers-color-scheme: dark)").matches
        ) {
            nextTheme = "dark";
        }

        setTheme(nextTheme);
    }, [initial]);

    useEffect(() => {
        if (!theme) return;

        const root = document.documentElement;
        const isDark = theme === "dark";

        root.classList.toggle("dark", isDark);
        root.style.colorScheme = isDark ? "dark" : "light";

        try {
            localStorage.setItem("theme", theme);
        } catch {}
    }, [theme]);

    const setThemeSafe = (next) => {
        if (next !== "light" && next !== "dark") return;
        setTheme(next);
        try {
            localStorage.setItem("theme", next);
        } catch {}
    };

    const value = useMemo(
        () => ({
            theme: theme || "light",
            setTheme: setThemeSafe,
            toggleTheme: () =>
                setThemeSafe(theme === "dark" ? "light" : "dark"),
        }),
        [theme]
    );

    return (
        <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
    );
};
