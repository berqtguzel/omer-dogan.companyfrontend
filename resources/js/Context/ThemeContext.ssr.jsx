import React, { createContext, useContext, useMemo } from "react";

const ThemeContext = createContext({
    theme: "light",
    setTheme: () => {},
});

export const useTheme = () => useContext(ThemeContext);

export const ThemeProvider = ({ children, initial = "light" }) => {
    const value = useMemo(
        () => ({ theme: initial, setTheme: () => {} }),
        [initial]
    );
    return (
        <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
    );
};
